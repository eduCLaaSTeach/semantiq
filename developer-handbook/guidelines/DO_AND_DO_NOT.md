# Do And Do Not Guide For Claude Vibe Coding

This guide is for using the `.claude/` gateway in fresh or existing projects. It combines this repository's rules with current Claude Code guidance: give Claude clear context, let it explore before coding, provide verification checks, keep instructions concise, manage context aggressively, and use separate review/verification steps for higher-risk work.

## Prime Rules

Do:

- Start every fresh or unclear project from zero.
- Read `.claude/README.md`, `.claude/PROJECT-CONTEXT.md`, and applicable `.claude/rules/` files first.
- Ask the developer what to build, why it matters, how it should work, what is out of scope, and how success will be verified.
- Keep asking until stack, database, deployment, validation, security, and success criteria are clear.
- Store only confirmed non-secret facts in `.claude/PROJECT-CONTEXT.md`.
- Verify tables and columns against the project's schema source before any database/schema/table/column work, per `.claude/rules/database-schema.md`.
- Validate work with commands, tests, screenshots, schema metadata, or other developer-confirmed checks.
- Show evidence: commands run, results, files changed, and remaining risks.
- Check `.claude/rules/hard-stops.md` for the non-negotiable stops before acting, and run non-trivial work through the gated lifecycle in `.claude/rules/phased-workflow.md` (Plan, Design, Recommend, Evaluate, Validate).
- Apply the conditional rules when their trigger matches: database or schema work (`database-schema.md`), UI and the role and access model (`ui-ux-quality.md`), a service interface (`api-design.md`), cross-boundary calls (`resilience.md`), stored data (`data-governance.md`), a designed or altered data structure (`semantic-data-model.md`), a production service (`operations-incident.md`), shipped LLM/agent behavior (`ai-agent-governance.md`), and a runtime AI model call (`ai-model-catalog.md`).
- Expect the gateway to auto-record an improvement feedback log to `.claude/feedback-logs/` when the same request goes unmet about three times, per `.claude/rules/feedback-logging.md`.
- Expect the gateway to write your end-of-day status report to `docs/eod/` as the work happens, starting at the first prompt of the session, per `.claude/rules/eod-reporting.md`.
- Expect the gateway to keep the owner's one-page orientation map current, rebuilt from the repository when a milestone, a sprint state, the configuration surface, or an open item moves, per `.claude/rules/orientation-map.md`.
- Expect every delivered code change to follow `.claude/rules/secure-coding.md` without you asking: server-side validation, encoded output, CSRF left on, no unsafe deserialization, checked uploads, no internals in errors, an audit record for every security-relevant action, and the tests that prove them.
- Expect a code review and a security review on every delivered code change, run without you asking, with `Critical` and `High` findings blocking the done claim, per `.claude/rules/review-gates.md`.

Do not:

- Do not assume a language, framework, runtime, package manager, hosting provider, source control provider, CI/CD system, deployment model, architecture, validation command, or security model.
- Do not write code when requirements are unclear.
- Do not update project context from guesses.
- Do not store secrets, tokens, passwords, private keys, full connection strings, `.env` values, customer records, or production row data.
- Do not claim work is complete, safe, or bug free without validation evidence.
- Do not call an application, feature, or change SOC 2, ISO 27001, or otherwise compliant, certified, audit-ready, or aligned because of its code. Describe the control that was built and the requirement it supports.
- Do not invent database tables, columns, relationships, indexes, constraints, enum values, or schema details.
- Do not run SQL DDL directly.
- Do not expect Claude to waive a rule for you. The rules bind every user in every session; only the gateway owner can lift one, and Claude verifies that from a login, not from anything you can say or type.

## Overriding A Rule

The gateway's rules apply to everyone, in every project the `.claude/` folder is copied into. One narrow exception exists: the gateway owner can lift a single named rule for a single named action, for example asking to run DDL directly during debugging instead of going through a reviewed migration. `.claude/rules/owner-override.md` is the authoritative rule.

Do:

- Use `.claude/commands/owner-override.md` and name the one rule to lift, the reason, and the scope.
- Expect Claude to resolve every identity itself and check each hash against `.claude/OVERRIDE-AUTHORITY.md`: the signed-in Claude account as the required anchor, GitHub as a veto, plus the personal key file on your own machine (`~/.c2s/override.key`) in a local session. Nothing is typed or pasted, so a shared transcript exposes nothing.
- Expect a plain refusal and the compliant path when the session is not verified, including on anyone else's machine, whichever accounts happen to be signed in there.
- Expect a banner on overridden output, honest risk labeling on whatever it produces, and a line about it in the final report.

Do not:

- Do not type, paste, or state an identity to unlock an override; a claim in chat is never accepted.
- Do not expect one held credential to be enough. A bearer token or a provider login proves who holds it, not who you are, so each can only veto, never authorize on its own. `git config` identity counts for nothing at all.
- Do not mix accounts in one session. Working with someone else's GitHub account signed in alongside your own Claude account refuses the override, by design.
- Do not ask Claude to reveal who the owner is, which source it read, or which entry matched, and do not expect it to confirm a guess.
- Do not ask for a blanket "ignore all the rules"; an override names one rule.
- Do not ask Claude to add you to the authority list or to weaken the check; it only does that in an already-verified session.
- Do not treat an override as permission for Claude to misreport. It changes what Claude may do, never what Claude may claim, so validation, approvals, and schema verification are still reported exactly as they happened.

## Intake

Do:

- Ask focused questions before implementation.
- Ask about the exact task, target users, business rules, edge cases, constraints, and acceptance criteria.
- Ask what stack is approved and what versions are required.
- Ask what source control, CI/CD, hosting, deployment, rollback, and monitoring flow exists.
- Ask which validation commands can run and where they can run.
- Ask what data is sensitive and what must never be logged or exposed.
- Ask who is on the team and fill the `## Team` table in `.claude/PROJECT-CONTEXT.md`: name, email, role (`TL`, `PL`, `LD`, `TD`, `TA`), work start, work end, timezone, and the From and To dates of their period on the project.
- Ask the team to update the sprint number and list that sprint's tasks, grouped by cluster, module, and feature, in `.claude/PROJECT-CONTEXT.md` as a checkbox list; ClickUp requires signing in, so Claude cannot open the link.
- Update `.claude/PROJECT-CONTEXT.md` after the developer confirms non-secret facts.

Do not:

- Do not treat folder names, old files, package files, screenshots, or prior projects as confirmation.
- Do not stop asking just because the question list is long.
- Do not let urgency override clarification.
- Do not ask for secret values; ask for placeholders.
- Do not proceed from partial answers if the missing detail affects implementation, database, deployment, security, or validation.

## Prompting

Do:

- Give Claude precise scope and concrete success criteria.
- Reference real files, docs, screenshots, errors, and examples when available.
- Ask Claude to inspect the current codebase before proposing changes.
- Ask for a plan for multi-file, risky, unfamiliar, or ambiguous work.
- Ask Claude to implement only after the plan and unanswered questions are resolved.
- Ask Claude to run checks and iterate until they pass.

Good prompt shape:

```text
Read .claude/README.md and .claude/PROJECT-CONTEXT.md.
This task is: <TASK>.
Do not assume missing stack/deployment/database details.
Ask me any required questions first.
After clarification, inspect the relevant files, propose a short plan, implement, run <VALIDATION>, and report evidence.
```

Do not:

- Do not say only "make it better" when you already know what better means.
- Do not combine unrelated tasks in one long session.
- Do not hide acceptance criteria in vague wording.
- Do not ask for implementation and then add critical constraints after the work is done.
- Do not let Claude continue down a wrong path; stop and correct early.

## Exploration And Planning

Do:

- Explore first when the codebase, architecture, or task is unclear.
- Keep exploration scoped to the likely area of work.
- Use subagents for broad research or independent review so the main context stays clean.
- Ask for a short implementation plan before risky or multi-file edits.
- Confirm the plan before coding when the blast radius is high.

Do not:

- Do not let Claude read hundreds of files without a clear search goal.
- Do not ask for a plan when the change is tiny and fully specified.
- Do not accept a plan that lacks validation steps.
- Do not use planning as a substitute for developer confirmation.

## Coding

Do:

- Follow the existing project patterns after they are confirmed.
- Keep changes small and reviewable.
- Prefer root-cause fixes over surface patches.
- Preserve existing architecture unless the developer approves a redesign.
- Add or update tests when behavior changes.
- Document code with structured docstrings per `.claude/rules/code-documentation.md`: doc-comment functions, methods, classes, interfaces, abstract classes, enums, and modules, document significant variables, and explain non-obvious logic using the project's idiomatic doc-comment style.
- Keep deployment, dependency, schema, authentication, authorization, and infrastructure changes behind explicit approval.

Do not:

- Do not introduce dependencies, services, queues, caches, build tools, infrastructure, or deployment requirements without approval.
- Do not rewrite unrelated code.
- Do not change formatting broadly unless formatting is the task.
- Do not remove user changes or revert files unless explicitly asked.
- Do not hardcode environment-specific URLs, credentials, tenant IDs, provider details, or secrets.
- Do not leave public or non-trivial functions, methods, classes, interfaces, abstract classes, enums, or modules undocumented.
- Do not add noisy comments that merely restate self-evident code, and do not leave stale or misleading documentation.

## Secure Coding

Applies to every delivered code change. `.claude/rules/secure-coding.md` is the authoritative rule; dependency vetting lives in `.claude/rules/enterprise-governance.md` and the compliance-claim rule in `.claude/rules/production-readiness.md`.

Do:

- Validate every input on the server: form fields, query strings, route parameters, headers, cookies, uploads, webhook bodies, queue messages, and responses from other services. Re-validate a step-by-step draft as a whole when it is completed.
- Bind a request to named fields, and reject what does not conform rather than repairing it silently.
- Pass values to the database as bound parameters, and check a sort column, sort direction, or field name from the URL against an allow-list of real names.
- Keep the template engine's escaping on, and render rich text through the confirmed sanitizer, or as plain text when there is none.
- Pass data to a script as JSON through attribute escaping in a data attribute, or with `<`, `>`, and `&` escaped in a JSON script block, check the scheme of any user-supplied link, neutralize formula cells in an export, and send a record out through an explicit field list.
- Keep CSRF protection on every state-changing route authenticated by the session cookie. A signature-verified inbound webhook is the only standing exemption.
- Set the session cookie `HttpOnly`, `Secure` over HTTPS, with no `Domain` attribute, and `SameSite=Lax` (`Strict` can break a sign-in callback from an identity provider on another site).
- Keep TLS certificate verification on for every outbound call, and fix the trust store instead of switching it off.
- Check every address a supplied URL resolves to, on every redirect, and redirect a browser only to the app's own routes or registered URLs.
- Where the app accepts files, check the type against the actual content, enforce the size limit, store under a generated name outside the web root, serve anything that can carry script as a download, and authorize the download like the record it belongs to.
- Run production with debug output off, show an unexpected error as a generic message with a correlation id, and keep validation messages specific.
- Record every security-relevant action a change adds or alters in the append-only audit trail, refused attempts included: who, what, which record, the outcome, when, the correlation id, and the session.
- Add tests for the security controls a change adds or alters, using synthetic payloads.
- Run the project's confirmed vulnerability audit on a dependency change, and report the result.
- Describe security work as the control it is and the requirement it supports.

Do not:

- Do not treat browser-side validation as the control, or mass-assign a request body onto a record.
- Do not concatenate input into a query, a shell command, a file path, or a template, and do not let a command read a user value as an option.
- Do not switch escaping off for a value a user could influence, put one in an `onclick` or `style`, set `innerHTML` from one, or render a user-supplied `javascript:` or `data:` link.
- Do not exempt a cookie-authenticated route from CSRF without a recorded exception, and do not create, change, or delete application data or access on a `GET`, other than the request's own audit record.
- Do not deserialize untrusted data into objects (`unserialize`, `pickle`, native serialization, polymorphic JSON), parse XML with external entities on, or evaluate, include, or load anything a user can influence.
- Do not switch off certificate verification, let a server-side fetch reach an internal address the environment configuration does not name, or redirect to an unchecked URL taken from the request.
- Do not trust an upload's extension or declared type, use its original name as the path, or serve an HTML or SVG upload inline from the application's own site.
- Do not show a user a stack trace, a query, a path, or configuration, let a record outside their scope answer differently from a missing one, or write a name or email address into a log.
- Do not treat a log line as an audit record, put a secret in one, or give the application a code path or screen that edits or deletes one.
- Do not take a dependency version with a known critical, high, or unrated advisory unless the fixed version is used or the developer accepts the risk and it is recorded as an exception.

## UI And UX

Applies only when the project has a user interface. `.claude/rules/ui-ux-quality.md` is the authoritative standard; the bundled `.claude/skills/ui-ux-design/` skill is the approved design system - written guidelines whose theme, colors, fonts, logos, and favicons are constants (all values in `reference/design-tokens.md`, brand images in `assets/`), identical in every application.

Do:

- Apply the UI standard exactly as written every time: the same archetype for the same kind of screen, the same primitive for the same control, the same status-role meaning, and the same icon for the same action; only the app identity, navigation, entities, and domain content vary. Following the approved rules deterministically is what keeps output consistent.
- Extend the one master shell: full-height left sidebar, a slim top bar over the canvas, and a visible divider plus edge shadow between the chrome and the canvas.
- Keep all standing navigation in the sidebar under the four fixed clusters (Workspace, Compliance, Application Administration, System Administration; use only the ones the app needs), config-driven and role-filtered, at most three accordion levels deep; use in-canvas tabs only for depth overflow beyond three levels or for a record's facets.
- Take the app-specific values from the App Definition in `.claude/PROJECT-CONTEXT.md` (app name, title-bar name, navigation tree, brand-assets path), or ask the developer.
- Build from the fixed token values written in the skill's `reference/design-tokens.md`, implemented in the project's own stack, in both themes, and use the bundled per-theme logos and favicons as supplied.
- Ask the developer where the brand asset files go in the project (`<BRAND_ASSETS_PATH>`); never pick the location - the developer may also copy the files manually and record the path.
- Expect every list screen to sort and filter. Every orderable column sorts, the list opens on a stated default order, and a search and filter bar sits above it with a facet for each dimension you actually narrow by (status, type, owner, source, date range). Both run over the whole result set, on the server when the list is paginated, so a filter finds the matches on page three; changing a filter or the sort returns to page one, the counts show the filtered total, and the state sits in the URL so a refresh or a link to a colleague reproduces the view.
- Expect two button looks and nothing else: exactly one solid button per action group, the action that commits, and one neutral secondary look (surface fill, one border, ink text) for every other labeled action, so `Cancel`, `Back`, `Clear filters`, `Reset`, `Apply`, `Export`, `Duplicate`, and `Test Configuration` look the same on every screen. A group that only changes what is on screen, such as a filter bar or a toolbar of view controls, carries no solid button at all; the page's solid action stays the primary CTA in the page header.
- Expect every control in a list's actions column to carry its word beside its icon (`View`, `Edit`, `Delete`) as visible text, kept at every breakpoint - a narrow screen scrolls the pinned column rather than dropping to bare icons. Past about three actions on a row, the first three stay labeled inline and the rest sit behind one `More` control.
- Build data entry into the app's own UI: a create, edit, multi-step, or settings form is its own route or a form region on the current page, inside the shell. A modal is for a decision that must be answered now, and it carries at most the three or so fields that are the decision itself (a typed confirm word, a reason, a new date).
- Expect every toast at the top right, in one host below the top bar, newest nearest the top edge - success and error alike.
- Expect a breadcrumb on any page that sits inside a nav group, carrying the full path from the cluster down, generated from the navigation config. Ancestors that are pages are links, so the trail itself is the way back; a cluster or group heading has no page, so it reads as text.
- Expect a form to report an error once, where the error is: each message inline under its own field, and a blocked submit announced by one error toast naming how many fields need attention while focus moves to the first invalid field. A form-level error that belongs to no field (the save failed, the service was unreachable) gets one inline alert at the form foot instead.
- Expect a step-by-step form to save a resumable draft: the advance button reads `Continue`, and it validates the step, saves it server-side, then moves on, so a closed tab, an expired session, a dropped connection, or a switch to another machine costs nothing already entered. The user comes back to the step they left, with the earlier steps filled in. The step rail runs down the left and is clickable: any step already saved, plus the furthest one reached, goes back for an edit, and coming forward again saves what you changed on the way.
- Tell Claude where a draft is stored, because it will ask and will not decide: a separate draft table, or the record's own table with a draft state. Either works; a draft in the live table means the required columns have to be nullable until completion, and every query, count, and report has to filter the state.
- Design the empty, loading, and error states for every data-driven view, not just success.
- Meet WCAG AA: full keyboard access, a visible focus indicator, and respected reduced-motion.
- On a connection/integration config screen (API credentials, email/SMTP, a third-party app connection, a webhook), show only `Reset` and `Test Configuration` in the footer; render `Save` only after `Test Configuration` succeeds on the current values, and hide it again the moment a tested field is edited.
- Grant clusters from the five-tier role baseline, highest first: system admin (the only tier that reaches System Administration), admin (everything inside the application, including Application Administration), collaborator, contributor, read-only. Tiers are cumulative, meaning each one sees everything below it and grants no higher than itself, and the default grants are Workspace to everyone, Compliance to collaborator and above, Application Administration to admin and above, System Administration to system admin only.
- Name an AI provider entry after the provider and model, the way someone picking it from a dropdown would recognise it (`OpenAI GPT-5.6`, `Claude Opus 5`, `Gemini 3.1 Pro`), not after the job it does today (`Primary chat`, `Summarizer`).

Do not:

- Do not invent, re-derive, or re-skin the theme, palette, surfaces, fonts, logos, or favicon; they are approved.
- Do not invent the app-specific values either (app name, title-bar name, navigation tree); the developer supplies them.
- Do not invent your own variant of a pattern the standard defines, reinterpret it, or repurpose a status role's meaning or color - never a one-off.
- Do not move standing navigation to the top bar, add a fourth accordion level, replace the sidebar with top tabs or a bare hamburger, or add/invent/rename a navigation cluster beyond the four fixed ones (Workspace, Compliance, Application Administration, System Administration; using only a subset is fine).
- Do not put action buttons in the top bar; a primary action (create/"New") belongs in the page header of the archetype, and the top bar stays app name plus notifications and profile only.
- Do not hardcode colors or use system fonts; use the approved tokens, Montserrat headings, and Source Sans 3 body.
- Do not ship a blank screen or a lone spinner where a skeleton, empty state, or placeholder belongs.
- Do not accept a list screen you can only read top to bottom, or one whose sort and filter reorder just the twenty-five rows on screen while the rest of the matches sit behind pagination.
- Do not accept one button role looking different from screen to screen, a borderless or outlined labeled button, a second solid button in one action group, or a filter bar control rendered as the solid one; a button's look also never changes because it is loading.
- Do not accept a bare icon in an actions column, or a label that disappears on a narrow screen. Icon-only is chrome, and the list is closed by kind: a dismiss or close mark on something dismissable, a clear mark on a field or a chip, and a form repeater's per-row remove control. A component's own primitives are a separate matter and keep their own look: the shell's icon controls, a sortable column header, the pager, a row's expand chevron, a tab strip, a segmented control, and a toast's single inline action.
- Do not accept a form in a popup, a drawer, or an off-canvas panel, however few fields it has, and do not accept a dialog that has quietly grown into a record editor.
- Do not accept a hand-written breadcrumb that shows less than the real path, or a back link on one line with a breadcrumb underneath it saying the same thing.
- Do not accept a toast anywhere but the top right, or one that moves by type, screen, or breakpoint. An error in a different corner from a success is the same defect as two screens disagreeing about a button.
- Do not accept an error-summary card listing the field errors again above or below the form. The inline messages are the record and the toast is the announcement; a summary makes you read every error twice.
- Do not accept a multi-step form that holds the entered steps in the browser only, loses a step when the save fails, or asks the user to start again from step one after a session expires. A draft also never holds a credential, key, token, or password.
- Do not add a "save anyway" or force-save path on a connection/integration config screen - saving without a successful `Test Configuration` on the current values is never allowed.

## Authentication And Authorization

Applies to every project that signs a user in. Authentication follows the application's own authentication model, recorded in `.claude/PROJECT-CONTEXT.md`. Every app provides its own authorization, resolved per feature; the Role And Access Model in `.claude/rules/ui-ux-quality.md` is the authoritative rule.

Do:

- Confirm the authentication model and record it in `.claude/PROJECT-CONTEXT.md` before building sign-in.
- Record every sign-in attempt and how it ended, successful or not, from a closed set of codes. The generic message is deliberate, so the record is the only place the cause survives. A failure before the identity is known carries no user, and that empty value is the fact.
- Reissue your local session identifier when the session becomes authenticated, so a value the browser held before sign-in does not survive it.
- Resolve the tenant from your own store where the app is multi-tenant.
- Give every feature a registry row with a stable code, express a role as a set of feature grants, and enforce every grant at the navigation, the handler, the per-record policy, and the query scope.
- Check every active, granted feature resolves to a real route. One that does not drops its link and keeps its cluster heading, so whoever holds the grant sees a heading with nothing under it.
- Wire your framework's authorization helpers to the application's identity, or bypass them and call the policy directly. Either is fine; half-wired is a silent deny-all.
- Provision your confirmed default role on a first login, and record it. Do the same when the configured default role does not exist: admit them with no grants, record it, and check the role resolves at migration or seed time.
- Keep grants as data changed in a screen, taking effect on the next request.
- Bound what an administrator can hand out to what they already hold: a role only up to their own tier, a grant only if they hold it themselves, on their own record exactly as on anybody else's.
- Show a role or grant above the acting administrator's authority as a locked cell, and merge it back in on save. What the form submits is the subset they may change, never the whole record.

Do not:

- Do not enforce access in the navigation alone, and do not load out-of-scope rows and hide them in the view.
- Do not leave your framework's `Gate`, `Policy`, or `can()` pointed at its own empty auth user. It will not error, it will deny everyone, and it reads as a policy bug.
- Do not compare a role name as a string in a handler. Check the grant.
- Do not treat a user with no local role as unauthorized. That locks out every new joiner. Do not refuse one because the configured default role does not resolve either, and do not substitute a different role.
- Do not let an administrator tick a tier or a feature they do not hold. Opening the Access Control screens is permission to administer access, not to award it, and their own record is the case the limit exists for.
- Do not save a role or a user by replacing the whole record with the cells you rendered. Everything above the actor's authority disappears, so an app admin demotes a system admin by pressing Save on a form that never showed the role it removed.
- Do not put a permission in code, a constant, or an environment variable, or make a grant change need a deploy.
- Do not log or print a token, its decoded claims, a one-time sign-in code, or a client secret.

## AI Model Calls

Applies only when the app calls an AI model or LLM at runtime. `.claude/rules/ai-model-catalog.md` is the authoritative rule; the bundled `.claude/skills/ai-model-integration/` skill is the approved pattern. A model is a catalog record read by one generic engine, so adding or changing a model is configuration, never code.

Do:

- Keep the endpoint, method, header rows, request payload, response paths, price, and the masked API key on one catalog record, and route every call through one engine that holds no provider knowledge.
- Type every body field (text, number, true/false, JSON) so the payload is correct per model: a number stays unquoted, a boolean stays a boolean, and a JSON field carries a nested value such as a message array.
- Keep the API key in its one masked field and reference it as `{{api_key}}`; enter it once, never re-render it into a form, and treat a blank field on edit as unchanged.
- Substitute placeholders in one pass, fail the call when one cannot be resolved, percent-encode into a URL, and reject a line break in a header value.
- Keep every response path on the record, including the error path, and always return the untouched raw response so a path that misses can be corrected in one edit.
- Report tokens and per-call cost with the currency, and report unknown when a price or a token count is missing.
- Give every call the confirmed timeout, and count each retry and each fallback as its own spend against the confirmed token budget and cost ceiling.
- Run a real test call before a record is saved, and record its outcome on the record.
- Confirm where the catalog lives; a database-backed catalog goes through the database schema workflow like any other table.
- Version a record already in use instead of editing it in place, so a bad change is undone by moving the active pointer back.

Do not:

- Do not hardcode an endpoint, request payload, response shape, or price in code, and do not branch on a provider name, label, or URL host anywhere in the call path.
- Do not keep a second call path, client, or payload builder for a second provider; if a provider will not fit the record, name the missing field and propose adding it.
- Do not re-render a saved API key into a form, or export, seed, commit, or log one, and do not partially mask one.
- Do not resolve an unknown placeholder to an empty string, re-scan a substituted value, or hand-escape a value for JSON.
- Do not parse, validate, repair, or re-prompt the generated content inside the engine; the calling code owns whatever the model produced inside the content block.
- Do not treat a successful call whose content path missed as a null answer or as a failure; report it as a path miss with the raw response shown.
- Do not report a cost of zero when the price or the token count is unknown, and do not estimate tokens the provider already reported.
- Do not retry without the confirmed policy and a dedupe key, since a retry after a timeout can bill twice.
- Do not offer a save-anyway, skip-test, or force-save path on a model record, and do not make an untested record callable.
- Do not render model output as markup, or log the prompt text, the response content, or the resolved headers.

## Database And Schema

Do:

- Read `.claude/rules/database-schema.md` before database work.
- Confirm the project's schema source of truth and migration tool from `.claude/PROJECT-CONTEXT.md`, or ask.
- Confirm the four schema rules back to the developer: do not execute DDL directly; do not invent table or column names; always check existing tables before proposing new ones; show the exact shape and get explicit confirmation before writing a migration.
- Verify every table and column against the schema source before referencing it, in the current session.
- Search the existing schema and the table dictionary before proposing new storage, including the shared reference sets a feature groups its reporting by.
- Deliver every schema change as a migration through the confirmed migration tool, paired with its rollback, and update the table dictionary in the same change.

Do not:

- Do not request production row data.
- Do not run DDL directly against any database, and do not create migrations or DDL files when no migration tool is confirmed.
- Do not write code against a table until the migration creating it exists in the same change or is already applied.
- Do not guess column names from old code, naming patterns, memory, or screenshots.
- Do not print connection strings, passwords, tokens, or any credential used to reach a database.

## Data Model And Analytics

Applies whenever a table is designed or changed. `.claude/rules/semantic-data-model.md` is the authoritative rule; the bundled `.claude/skills/semantic-data-model/` skill is the approved pattern. It sits on top of the database schema rules above and relaxes none of them. The point: a screen needs the current state of one record, every analytical question is a count across rows and across time, and a value that was never captured cannot be recovered by a better query later.

Do:

- Expect Claude to draft the feature's analytics question set and ask you to confirm, cut, or add before it proposes any shape, and to tell you which questions the shape cannot answer.
- Say which questions are day-one and which are later, so the structure is sized to what you actually need.
- Expect a one-sentence grain per table ("one row is one enrichment attempt on one contact"), carried into the proposal and the table dictionary.
- Expect atomic rows with totals derived from them, and a rate stored as its numerator and denominator rather than as a percentage column.
- Expect every value you group by to be a codified code plus a display label, reused from the platform's existing dimension (source, status, outcome, reason, owner) instead of a private list or free text.
- Expect state changes, step attempts, coded outcomes and failure reasons, and duplicate matches to be written as rows, with soft delete rather than hard delete.
- Expect a timezone-aware business event date distinct from the audit columns, and one named primary business date per fact.
- Confirm the Analytics And Semantic Model facts in `.claude/PROJECT-CONTEXT.md`: whether a semantic model or BI layer consumes the data and who owns it, how reporting reads it, the reporting time zone and business-day boundary, the key convention, the platform's conformed dimensions, history retention, and where the ERD and data-model write-up live.
- Expect the change to ship the data structure, the entity relationships with cardinality and optionality, the ERD, and the semantic hand-off (grain per table; each measure's unit, default aggregation, additivity, and agreed definition; each dimension's hierarchy and whether it is conformed), as metadata through the knowledge-base gate.
- Use `/semantic-model-plan` to run the steps in order: questions, reuse search, shape preview, then the migration.

Do not:

- Do not expect a shape before the questions are confirmed; Claude asks first by design.
- Do not accept free text, or a display label stored on every row, for a value a report groups by.
- Do not accept a single boolean flag where the question needs attempts and outcomes; "not enriched" hides never attempted, failed, and skipped, which are three different answers.
- Do not accept a stored count, total, or percentage as the only record of the rows behind it.
- Do not let a status, stage, score, or owner that a question asks about over time be overwritten in place.
- Do not accept a hard delete on a record a report has already counted.
- Do not accept a nullable dimension key where the question counts rows; reserved unknown members exist for exactly that.
- Do not accept a composite key string, a delimited list column, or an analytic attribute buried in a JSON blob.
- Do not ask for a warehouse, cube, extract job, second reporting store, or dashboards as part of feature work; that is separate work with its own approval.
- Do not expect versioned history on every attribute, a date dimension, or a snapshot table by default; only a confirmed question earns one.
- Do not expect Claude to invent the questions for you, or to answer a question you did not ask for.

## Deployment

Do:

- Treat deployment as unknown until the developer confirms it.
- Ask for source control, CI/CD, hosting, deployment method, runtime, build, artifact, environment placeholders, rollback, logs, monitoring, and smoke tests.
- Treat source control, CI/CD, build, artifact storage, hosting, database, and runtime as separate choices.
- Use provider-specific instructions only after the provider and workflow are confirmed.
- List the manual server-side or control-panel steps the developer must perform when a change needs them (environment variables, cron, SSL, DNS, database, permissions), provider-neutral until the provider is confirmed.
- Ask before production-impacting actions.

Do not:

- Do not assume any hosting provider, deployment target, source control provider, CI/CD system, container model, cloud service, local workflow, build tool, worker model, or server access.
- Do not deploy, upload, publish, migrate, edit production environment values, or change hosting settings without explicit approval.
- Do not put real environment values or credentials in docs or chat.
- Do not provide final deployment steps while deployment details are still unclear.

## Git, Branching, And Release

Follow `.claude/rules/git-branching-release.md`. All Git and GitHub actions are allowed without per-action confirmation, but state the plan first.

Do:

- Work on short-lived `feature/*`, `bugfix/*`, `release/*`, or `hotfix/*` branches and promote through `DEV`, `QA`, `STAG`, then `PROD`.
- After pushing a working branch, hand the Pull Request to the developer: show the PR URL and the ordered next steps.
- Tag production releases from `PROD` with CalVer `vYYYY.R.P`, and create a matching release where the project expects one.
- Apply the project's confirmed workflow knobs from `.claude/PROJECT-CONTEXT.md` (signing, merge strategy, branch deletion, reviewers).
- Reach for the copy-ready prompts in `developer-handbook/prompts/GIT_PROMPTS.md` for common sync, stash, branch, PR, promotion, and release workflows.

Do not:

- Do not create lowercase environment branches or recommend skipping stages such as `DEV -> PROD`.
- Do not force-push a shared or environment branch, or run a production deploy or migration without explicit approval.

## Validation

Do:

- Ask for validation commands during intake.
- Give Claude a check it can run or a manual verification step it can report.
- Prefer deterministic checks: tests, builds, linters, type checks, scripts, screenshots, or schema metadata verification.
- Have Claude show evidence rather than only saying it passed.
- For high-risk work, use a review subagent or separate review step.

Do not:

- Do not accept "looks good" as validation.
- Do not claim tests passed unless they actually ran.
- Do not ignore failing checks unless the developer explicitly accepts the risk.
- Do not mark a task complete if validation is impossible without documenting why and what follow-up is required.

## Context Management

Do:

- Keep `.claude/PROJECT-CONTEXT.md` concise and factual.
- Use topic-specific rules and commands instead of one huge instruction file.
- Clear or restart sessions between unrelated tasks.
- Correct Claude early if it goes off track.
- After repeated failed corrections, start a fresh session with a better prompt and the learned constraints.

Do not:

- Do not overload project context with tutorials, speculation, logs, or long explanations.
- Do not mix unrelated workstreams in one conversation.
- Do not rely on conversation-only instructions for durable rules.
- Do not store sensitive data in memory, project context, docs, logs, or summaries.

## Code Review And Security Review

Runs on every delivered code change, without being asked. `.claude/rules/review-gates.md` is the authoritative rule, and it drives the `code-reviewer` and `security-reviewer` subagents in `.claude/agents/`.

Do:

- Expect both passes over the actual diff before Claude calls any code change done, run as subagents rather than Claude reviewing its own work.
- Expect one severity per finding: `Critical`, `High`, `Medium`, `Low`, with the file, the line, the concrete impact, and a remediation in your project's patterns.
- Treat `Critical` and `High` as blocking the done claim until fixed and re-reviewed, or until you explicitly sign off on that named finding.
- Say the sign-off in words when you choose to carry a `Critical` or `High`; it is recorded as a documented exception in `.claude/PROJECT-CONTEXT.md`.
- Expect Claude to re-run the pass over the fix, since a patch for a `Critical` is a common way to introduce a different one.
- Expect the passes to be reported even when they found nothing, and to name any list that could not be checked and why.
- Run `/security-check` when you want the security pass earlier, over a fix, or over code Claude did not write.
- Confirm the facts the reviews depend on in `.claude/PROJECT-CONTEXT.md`: authentication model, data sensitivity and classification, security and identity conventions, secure coding conventions, configuration contract, and architecture boundaries.

Do not:

- Do not expect to have to ask for a review; needing to remember it is the failure this replaces.
- Do not accept "no security issues" as a conclusion when no pass actually ran, or silence in place of a stated clean result.
- Do not accept a rubber stamp: a pass that finds nothing on a change touching authentication has either verified that specifically or has not run properly, and those must not look the same in the report.
- Do not treat the reviews as a substitute for validation; build, tests, lint, and type checks still run.
- Do not expect the gate to block your Git actions. Every Git command stays allowed without per-action confirmation; the gate governs when Claude may call work done and what it must tell you before you merge.
- Do not expect the subagents to edit files. They review and report; fixing is separate work that gets reviewed like any other change.

## EOD Status Reports

Always on in every project the gateway is copied into. `.claude/rules/eod-reporting.md` is the authoritative rule. Claude writes the day's status report as the work happens, so nobody has to remember to write one before leaving.

Do:

- Expect Claude to open today's report at `docs/eod/eod-date-<D><Month><YYYY>.md` (for example `eod-date-17August2026.md`) at the first substantive prompt, and ask two things in one message: whose EOD this is, and what is on your list today.
- Answer that question or ignore it; it never blocks your actual request, and Claude fills Planned from the work you start and marks it `(inferred)`.
- Give your name when asked. On a shared Claude plan the account, its email, and the GitHub login are the same for everyone and identify nobody, and `git config` is editable, so Claude asks instead of guessing.
- Fill the `## Team` table in `.claude/PROJECT-CONTEXT.md` during intake: name, email, role (`TL`, `PL`, `LD`, `TD`, `TA`), work start, work end, timezone, and the From and To dates of their period on the project. Claude reads your row from there and will not invent a colleague's.
- Fill someone's To date the day they leave or move to another project. They stop appearing in new reports from the next day, and every report they were part of stays exactly as it was.
- Keep a departed developer's row rather than deleting it; the row is what keeps their past sections attributable. If they come back, clear To instead of adding a second row.
- Use `/eod who` to switch the session to the right person when a shared machine is logging to the wrong one.
- Use one status per task from the closed list: `Not Started`, `In Progress`, `Partially Done`, `For Review`, `In Review`, `Blocked`, `Done`, `Dropped`.
- Expect `Started` and `Completed` on every task row, each carrying a date and a time (`17 Aug 09:20`). `Started` is stamped once, when the task first leaves `Not Started`; `Completed` only when it reaches `Done` or `Dropped`.
- Tell Claude when a task started or finished outside the session. It stamps the clock only for changes it watched, and asks for the rest.
- State why anything is `Blocked`, every time.
- Run `/eod` at the end of the day to bring the report up to date and commit it, `/eod team` to see the whole team's day, and `/eod date <date>` to correct an earlier one.
- Write both an EOD report and a session handoff when you end a day with work in flight; they answer different questions.

Do not:

- Do not expect Claude to ask permission before writing the report; it is a side effect of the work, pre-approved in `.claude/settings.json`.
- Do not expect measured hours, session counts, prompt counts, or activity levels in it. It records task status and the two task timestamps and nothing else; the work start and end in the team table are your declared window, not a measurement of your day.
- Do not read `Started` and `Completed` as a time sheet. They mark when a task moved, never how long anyone worked, and they are never totalled into hours, a rate, or a score.
- Do not accept a timestamp Claude did not watch happen and you did not state. A blank cell is information; an invented one is unfalsifiable a week later.
- Do not accept `Done` on work that was written but never validated; that is `Partially Done` or `In Progress`, per `.claude/rules/production-readiness.md`.
- Do not let customer data, production values, secrets, or a row from a table into a report; name the work, not the data it touched.
- Do not treat the session's developer as identity or authority. It is a name someone typed, so it can never verify an override or stand in for an approval.
- Do not write a status into another developer's section, or delete a section that is still `No entry`; an empty section tells the reader who has not reported.
- Do not resolve a merge conflict in a daily report by dropping somebody's entry; keep both sections and both sets of task rows.
- Do not confuse the EOD report with the session handoff in `developer-handbook/prompts/DAY_HANDOFF_PROMPT.md`; a report is status for a reader, a handoff is continuity for the next session.

## Orientation Map

Always on in every project the gateway is copied into. `.claude/rules/orientation-map.md` is the authoritative rule. Claude keeps one page saying where the project stands, written for whoever owns it, and rebuilds it when the project moves rather than when somebody remembers to ask.

Do:

- Expect one page at `docs/orientation-map.html`, or wherever the Orientation Map section of `.claude/PROJECT-CONTEXT.md` records it, answering three questions in order: Position, Surface, Open.
- Confirm four facts during intake so it runs itself: where the page lives, who it is written for, what counts as a milestone being formally accepted here, and which directories make up the configuration surface.
- Expect a rebuild when a milestone or sprint item is accepted, a sprint task changes state, a file joins or leaves the configuration surface, a blocker opens or closes, or a documented exception or accepted `Critical` or `High` finding is recorded.
- Expect the rebuild to go back to the repository every time, not to adjust the numbers on the page that is already there.
- Read the difference after a refresh. Claude states what changed and says outright when something previously open has closed; that is the part worth your attention, not the page.
- Treat it as a snapshot. It carries the date and the commit it was generated from, and reloading it changes nothing.
- Use `/orientation-map check` when you only want to know whether the page can still be trusted, and `/orientation-map for <reader>`, `scope <area>`, or `since <date>` for a variant.
- Say so if a line on the page is the only place a fact exists. Put the fact in the store it belongs to, then regenerate.

Do not:

- Do not expect a dashboard. The page reads nothing at runtime, has no live data, and must not be given any.
- Do not expect a rebuild on every commit. A page rewritten on every push is noise and stops signalling that something moved.
- Do not accept a guess in place of a gap. Anything genuinely unknown is written as unknown, with what would settle it.
- Do not let it become a fifth version of the truth beside project context, the working-memory log, the knowledge base, and the EOD reports. It renders those; it does not hold anything itself.
- Do not confuse it with the EOD report or the session handoff. One developer's day, next session's continuity, and the whole project's position are three different documents.
- Do not let a config file's values, customer data, or production values onto it. Name the file and what it is for, never what is in it.
- Do not schedule it weekly. Most weeks nothing on it moved, and a page rebuilt on a calendar is a page nobody trusts.

## Review And Closeout

Do:

- Ask, after each completed implementation, whether the knowledge base should be created or updated now, or deferred.
- Wait for the developer to verify, validate, and explicitly approve before writing knowledge-base files.
- Ask whether the documented work is a solution, a module, or a feature (a solution has multiple modules; each module can have multiple features) and let the developer classify it.
- Keep the knowledge-base README as the index, linking every knowledge-base file, and update it with every knowledge-base change.
- Keep knowledge-base write-ups current as the project is built; update a stale write-up as part of the change that made it stale.
- Report files changed.
- Report commands run and results.
- Report the tables verified and the migrations authored when database/schema work is involved.
- Report database/schema impact.
- Report deployment impact.
- Report knowledge-base/table-dictionary status.
- Report documentation/docstring coverage for code changes.
- Report security impact.
- Report UI states covered (success, empty, loading, error, small-screen) for UI work.
- Report the review passes: that both ran and over which files, the findings with severities, how each `Critical` and `High` was resolved, and explicitly when a pass ran clean.
- Report the EOD line: the report path, the developer it was logged for, and any task whose status changed.
- Report the orientation map line when a trigger fired: the page path, the trigger, what changed on it, and whether anything previously open is now resolved.
- Report assumptions, risks, and manual follow-up, including manual server-side steps the developer must perform.

Do not:

- Do not bury risks under a long success summary.
- Do not omit manual steps the developer must perform.
- Do not write knowledge-base files before the developer has verified, validated, and explicitly approved the update.
- Do not classify work as a solution, module, or feature yourself, and do not invent solution or module names.
- Do not leave a knowledge-base file unlinked from the knowledge-base README index.
- Do not say knowledge base was updated if it was intentionally deferred.
- Do not say no database impact when database-backed behavior or schema metadata was involved.

## Quick Developer Checklist

Before saying "go", confirm:

- The task is clear.
- The stack is confirmed.
- The database scope is confirmed.
- The analytics questions the data must answer are agreed if the work designs or changes a table, along with the Analytics And Semantic Model facts in `.claude/PROJECT-CONTEXT.md`.
- The schema source and migration tool are confirmed if database work is involved.
- Deployment target and method are confirmed if deployment is involved.
- Validation commands are known.
- Secrets are represented only by placeholders.
- The secure coding facts the work depends on are confirmed, such as the templating engine and its escaping, the CSRF mechanism, whether the server fetches supplied URLs, whether uploads are accepted and their limits, where the audit trail lives, and the dependency audit command.
- The App Definition (app name, title-bar name, navigation tree, brand-assets destination path) is confirmed if the work touches the UI; the theme and the asset files themselves are the approved standard and need no confirmation, but where the assets live in the project (`<BRAND_ASSETS_PATH>`) is always developer-confirmed.
- The authentication facts are confirmed if the work touches sign-in or access: how users sign in, the local session technology, the default role a new user is provisioned with, and which tier manages grants.
- The AI Model Catalog facts are confirmed if the work calls a model at runtime: where the catalog lives, the providers and model ids, what `{{env.NAME}}` resolves against, the project-wide timeout and retry policy, the currency, and who manages the catalog.
- The sprint number and the current sprint's tasks are listed in `.claude/PROJECT-CONTEXT.md` as a checkbox list, grouped by cluster, module, and feature.
- The orientation map facts are confirmed: where the page lives, who it is written for, what counts as a milestone being accepted here, and which directories are the configuration surface.
- The branch target and release/promotion path are clear if promoting or releasing.
- Success criteria are explicit.
