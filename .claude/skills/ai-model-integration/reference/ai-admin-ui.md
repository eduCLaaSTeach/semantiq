# AI Administration And AI Output

The screens that manage the AI controls, and the patterns users see around AI output. Every screen here is an existing archetype filled with AI settings, and every pattern is an existing component. Nothing here introduces a style, a token, a color, a component variant, an archetype, or a navigation cluster.

Read `.claude/rules/ui-ux-quality.md` for the standard and precedence, and open only the `.claude/skills/ui-ux-design/reference/` files for the components a screen actually uses. Token values come from that skill's `design-tokens.md`, never from memory.

The binding rules are `.claude/rules/ai-guardrails.md` for guardrails, limits, kill switches, and disclosure, `.claude/rules/ai-agent-governance.md` for routing and user feedback, and the role and access model in `.claude/rules/ui-ux-quality.md` for who may reach each screen. The provider list and the add/edit provider form stay in `catalog-ui.md`. The field names here are concepts, not required identifiers. Map them to the project's confirmed naming convention.

## Where They Sit

| Screen | Cluster | Group | Archetype | Tier |
| --- | --- | --- | --- | --- |
| AI Providers | System Administration | AI | List / index, then form with test-before-save | system admin, per `catalog-ui.md` |
| AI Routing | System Administration | AI | Settings / config: a list of steps, then a route form per step | system admin |
| Guardrails, with the dry run | System Administration | AI | Settings / config | system admin |
| Word Lists | System Administration | AI | Settings / config | system admin |
| Limits And Budgets | System Administration | AI | Settings / config | system admin |
| Kill Switches | System Administration | AI | Settings / config | system admin; the flip grant goes to the confirmed kill switch owner |
| AI Usage | Compliance | AI Activity | Dashboard, with a sortable report table, view-only | admin and above, or as the project narrows it |
| Guardrail Events | Compliance | AI Activity | List / index, then detail, view-only | admin and above, or as the project narrows it |
| Quarantine, only when the project confirmed one | Compliance | AI Activity | List / index, then detail, view-only | its own grant, per `.claude/rules/ai-guardrails.md` |
| AI Pauses | Application Administration | AI Oversight | List / index | admin and above |
| AI Feedback | Application Administration | AI Oversight | List / index, then detail | the confirmed queue owner, at a tier that reaches Application Administration |
| AI reference lists, such as the feedback reasons | Application Administration | named for what it holds | List / index, then form | admin and above |

- Each screen is its own feature in the registry, with its own code and its own grant, per the role and access model in `.claude/rules/ui-ux-quality.md`. Gate it at the handler as well as in the navigation, at all four layers. A user who may tune the word lists does not thereby change the budget.
- Never one all-or-nothing `Settings` page holding every AI control, and never a horizontal tab strip standing in for navigation between these screens. Each is a sidebar leaf. A tab strip is only for one record's facets or for depth beyond the third accordion level, per `.claude/rules/ui-ux-quality.md`.
- One System Administration group, `AI`, holds every AI configuration screen, in this order: AI Providers, AI Routing, Guardrails, Word Lists, Limits And Budgets, Kill Switches. There is no second AI configuration group, and AI Providers does not sit in `Integrations`, which keeps only the non-AI connections, such as the project's other outbound connections and its email and chat channels.
- `AI`, `AI Activity`, and `AI Oversight` are the default labels. Name a group for what it holds, and never the same as its cluster. The four clusters are a closed set; never add one for AI.
- The `AI` group sits in System Administration because these screens hold provider credentials, model routing, spending authority, and the switches that turn features off for everyone. That is system configuration, not application administration.
- Every AI screen in Compliance is view-only. Its row and page actions are `View`, and an audited `Export` at most, never an action that changes state. A screen that acts on a record, such as resuming a pause or recording a review outcome, sits in Application Administration instead.
- `AI Oversight` in Application Administration holds the two AI screens that act: AI Pauses, where an administrator resumes a paused user, and AI Feedback, the review queue where the reviewer records a coded outcome. Both are admin and above by default.
- A confirmed owner of a screen, such as the kill switch owner or the feedback queue owner, holds the tier that reaches that screen's cluster. Where the confirmed owner does not, move the screen to a cluster the owner can reach. Never widen System Administration to a lower tier to fit one owner.
- These screens add no sign-in and no user management. Users and their roles stay on the role and access screens described in `.claude/rules/ui-ux-quality.md`.
- `Resume` on a paused user and the switch flip on Kill Switches are their own action grants on their own features. Where the shared action list has no fitting action, add one to that list, per the role and access model in `.claude/rules/ui-ux-quality.md`, rather than inventing one per screen.

## Settings Screens

AI Routing, Guardrails, Word Lists, Limits And Budgets, and Kill Switches share one contract wherever they hold a form. A kill switch flips from its row, per Kill Switches below; its fallback message is edited on a form like the rest.

- The Settings / config archetype, page-hosted inside the shell, with the breadcrumb line from the cluster down. Never a modal, a drawer, or an off-canvas panel.
- The form footer carries a neutral secondary `Reset`, which restores the last-saved values on screen, beside the one solid `Save`. A form reached from a list row, such as a step's route, carries a neutral secondary `Cancel` instead of `Reset`. A settings form that holds a provider credential takes the test-before-save footer instead, per Credentials On These Screens below.
- A dirty form guards every exit with the discard prompt from `.claude/skills/ui-ux-design/reference/modals-dialogs.md`: `Cancel`, a sidebar link, a breadcrumb link, and any other in-app navigation. Edits are never dropped silently. Reload and tab close take the browser's own leave-page warning, the one place no page dialog can run.
- Every confirmation and every prompt is the kit's own dialog. Never the browser's native `confirm`, `prompt`, or `alert`, which cannot carry the target's name, the consequence, a verb-labeled action, or the approved look.
- The server validates every value against its allowed range and caps every list, per Settings Are Data Over Defaults in `guardrails.md`. A value outside its range is refused on save, naming the field. It is never clamped silently to the nearest allowed value, because the administrator would believe the value they typed is in force. Show the allowed range as helper text under the field.
- Report errors inline under each field, and announce a blocked submit once with one error toast, with focus moved to the first invalid field. No summary card.
- A stored value that no longer validates falls back to its default. Show a warning banner on the screen naming the field and the default in force, never a silent reset.
- Store settings as typed values, or one row per settings area, as the project confirms. Never mix them with runtime state: today's spend, a running strike count, an undo snapshot, a pre-draft, and job state each live in their own table.
- Every save is an audit record carrying what each changed value was, what it became, and the authority the actor held, per Changes Are Audited in `.claude/rules/ai-guardrails.md`. A refused save is recorded too.
- A save confirms with one success toast at the top right. A saved change takes effect on the next request; never imply a restart or a deployment is needed.

## Guardrails Screen

```text
Guardrails
┌──────────────────────────────────────────────────────────────────────────────┐
│ ▲ Warning: Moderation is unavailable since <TIME> <TIME_ZONE>. Messages pass │
│   unchecked under its fail-open mode and each one is recorded.               │
└──────────────────────────────────────────────────────────────────────────────┘

Screening
  Strictness                     [ Lenient | Standard | Strict ]

  Layer          Record                       Mode        Fail mode   On
  Small talk     no model call                Enforce     -           [ On  ]
  Length cap     no model call                Enforce     -           On, required
  Rules          no model call                Enforce     -           On, required
  Moderation     [ <MODERATION_RECORD>  ▾ ]   Enforce     Open ▾      On, required
  Relevance      [ <CLASSIFIER_RECORD>  ▾ ]   Annotate ▾  Closed ▾    [ On  ]
                 Added to an enforcing pipeline. <N> verdicts since <DATE>.
  Output check   [ <MODERATION_RECORD>  ▾ ]   Enforce     Closed ▾    On, required

Strikes
  Max strikes     Abusive weight    Other weight    Pause (minutes)   Count after pause
  [ <N>      ]    [ <N>        ]    [ <N>       ]   [ <N>         ]   [ Reset ▾ ]

Messages
  Blocked message, paused message, limit messages, small-talk replies

                                                        [ Reset ]  [ Save ]
```

- One row per layer: the small-talk shortcut, the length cap, the rules layer, moderation, relevance, and the output check. Each row carries its plain description and, for a model-backed layer, a native select of the active catalog records that passed their test call, by name. The checker's record is chosen here and only here, never on AI Routing.
- There is no master switch for the guardrails. Stopping AI for everyone is the kill switch's job, per Kill Switches below, and it sends users to the fallback path rather than letting messages reach the model unchecked.
- A layer may be turned off only where `.claude/PROJECT-CONTEXT.md` records it as optional. A required layer renders locked on, with the reason beside it. Turning an optional layer off is a confirm-guarded change that names the consequence: messages reach the model without that check.
- Each layer's on or off value is a switch with its visible state word, per the Switch section of `.claude/skills/ui-ux-design/reference/forms.md`. It saves with `Save` like every other field on the screen, and the dirty-form guard covers it. A required layer's switch renders on and disabled, with its reason as visible helper text.
- A model-backed layer with no record chosen cannot be turned on until one is. A saved checker record that is no longer active or tested shows as the current value with a danger status line and its reason. At runtime that layer is `unavailable` and takes its fail mode, never silently off, and the degraded-check banner below shows. An administrator holding the grant for AI Providers gets a link to that screen.
- Strictness is the shared segmented control from `.claude/skills/ui-ux-design/reference/topbar-sidenav.md`, over the project's confirmed levels, with equal segments at the 32px control height. What each level sets is a confirmed fact; never invent the thresholds behind a label.
- Strike settings are number inputs with `min`, `max`, `step`, and `inputmode="numeric"`, and no spin buttons, per `.claude/rules/ui-ux-quality.md`. What happens to the count when a pause ends is a select over the confirmed choices.
- The user-facing messages are text areas with a visible character count against their cap. Name the placeholders a message may carry, such as `<TIME>` and `<TIME_ZONE>`, in its helper text, and refuse an unknown one on save.
- Each check shows its fail mode, closed or open, as a select. The two fixed cases render locked, with the reason: a refusal from a screening model always blocks, and injection screening for an agent with write tools always fails closed.
- Each check shows its mode, annotate or enforce. Annotate is offered only for a check added to a feature whose pipeline already enforces, or for a changed threshold. The baseline layers, injection screening for an agent with write tools, and the rule that a screening refusal blocks render locked to enforce, with the reason, per Annotate Mode in `guardrails.md`.
- A check in annotate mode shows how many verdicts it recorded since it was switched, with a link to Guardrail Events filtered to it, so the verdicts are reviewed before it enforces. Moving it to enforce is a settings change, and so it is audited.
- While any check is unavailable, a warning banner sits at the top of the screen naming the check, when it became unavailable, and the fail mode applied. It is a persistent banner, never a toast, and it clears only when the check recovers.

### Word Lists

The ban list and the allow-list are their own leaf, `Word Lists`, in the same group. They grow long, they change more often than the thresholds, and they carry their own grant.

```text
Ban words                                                        128 terms
  [ Add a term                         ]  [ Add ]
  [ Filter terms                       ]
  ( <TERM> ✕ )  ( <TERM> ✕ )  ( <TERM> ✕ )  ( <TERM> ✕ )  ( <TERM> ✕ )
```

- Each list is a text field with a neutral secondary `Add`, and every entry renders as a removable chip in the chip treatment from `.claude/skills/ui-ux-design/reference/search-filter.md`. The chip's remove mark is the sanctioned icon-only clear mark, with an accessible name naming the term it removes.
- Adding trims and lowercases the term, and refuses a duplicate with an inline message rather than adding it twice. The entry length cap and the list size cap from the settings apply inline, before save.
- Show the count of terms beside the list heading. Once the list no longer fits on screen, show a filter field that narrows the chips shown. The filter edits nothing.
- The kit ships no word list. A `Restore starter list` action appears only when the project supplies a starter list, as a neutral secondary behind a confirmation naming how many terms it adds and that existing terms stay.
- Changes save with `Save`, like any settings form. The audit record carries the terms added and the terms removed.

## Dry Run Panel

A region on the Guardrails screen, below the settings. It tests a sample message against the values currently on screen, saved or not, so a change can be tried before it is saved.

```text
Dry run
  Sample message
  [                                                                          ]
  Uses the settings on this screen, saved or not. Model-backed layers are billed.
                                                              [ Test message ]

  Layer          Verdict        Category        Time      Cost
  Small talk     ● Pass                         1 ms      -
  Length cap     ● Pass                         1 ms      -
  Rules          ● Pass                         2 ms      -
  Moderation     ● Hit          harassment      310 ms    $x.xxxxxx
  Relevance      ● Not reached
```

- A sample message text area, then `Test message` as a neutral secondary. It proves the settings and saves nothing, so it is never the solid action.
- One result row per layer, in pipeline order: the verdict, the category for a hit, the duration, and the cost for a model-backed layer. Pass is success, hit is danger, unavailable is warning, and not reached is neutral, each as a status badge with its word, never color alone.
- Show the category code, never the word or pattern that matched, matching what `guardrails.md` returns for a dry run.
- Helper text beside the button says the model-backed layers are billed. A dry run counts against the budget, and a layer the budget refuses shows as unavailable with that reason.
- A dry run records no strike and writes no verdict row. Its audit record carries the actor, the time, the settings tested, and the verdict summary, never the sample text.
- The sample text is not stored, not put in the URL, and not kept after the page closes.
- While it runs, the button shows its loading state and the result list is a skeleton shaped like it. Announce the outcome through the live region.

## Limits And Budgets Screen

- Fields for the confirmed limits only: the per-user rate as a count per window, the per-record cap, the daily budget in calls, tokens, and cost, the alert percent, and the workflow caps on steps, tool calls, output tokens, input size, and concurrent runs.
- The cost budget is a number input with `inputmode="decimal"` and no spin buttons, with the project's currency symbol beside it, validated on the server as an exact decimal. Never stored or computed as a floating-point value.
- A read-only line states when the budget resets: `Resets at <TIME> <TIME_ZONE>`, the confirmed business-day boundary in the confirmed reporting time zone, per `cost-and-usage.md`.
- A read-only line states today's use: calls, tokens, and cost against the budget, with the percent used. An unknown cost reads `Unknown`, never zero. It reads the runtime usage rows, never the settings store.
- Lowering a limit below today's use is allowed. Say inline, before save, that new calls are refused until the reset.

## AI Routing Screen

A list of the feature's steps, then a route form per step on its own page.

```text
AI Routing
  Step             Shape             Records                     Worst case        Actions
  Outline          Single            <RECORD_NAME>               1 call · $x.xx    [Edit]
  Write section    Dual plus judge   <RECORD_NAME> +2            3 calls · $x.xx   [Edit]
                   ▲ <RECORD_NAME> is deprecated and retires on <DATE>.
```

- The list carries each step, its shape, its records by name, the worst-case calls and cost, and a status line where something needs attention. Each `Edit` is a labeled control.
- The route form offers only the shapes the project allows: single, fast, or dual drafting plus a judge.
- Each role, main, fast, draft A, draft B, and judge, picks its catalog record from a native select, by name. The select lists only active records that passed their test call and have a recorded passing eval result for this step and the role's prompt version. Never a free-text model name and never a provider name, per `orchestration.md`.
- The eval result is recorded per step, catalog record, and prompt version, with its timestamp, per Evaluation in `.claude/rules/ai-agent-governance.md`. The server checks it again on save and refuses a pair with no passing result, naming the role.
- A saved route whose record is no longer active or tested shows that record as the current value with a danger status line and its reason. The step refuses to run until the route moves to a callable record.
- A record the provider marked deprecated shows a warning status line with its retirement date. A retired one shows a danger status line. Moving a route to a replacement follows Model Lifecycle in `model-record.md`: the replacement passes its eval set first, and the select does not offer it until that result is recorded.
- Per role: the max output, the prompt version from a select of released versions, and temperature and seed, left empty where the model rejects them. Changing the prompt version narrows the record select to the records with a passing eval result for that version.
- For dual drafting only: cross critique as a switch, the judge mode, patch or rewrite, and the patch-mode edit cap as a number input.
- Pre-drafting is a switch, off by default, with helper text saying a discarded pre-draft is still billed.
- A read-only line shows the step's worst case: the call count, and the worst-case tokens and cost from the records' max output and prices, or `Unknown` where a price is unknown. It updates as the shape changes, and the server computes it again on save.
- A route change is audited and takes effect on the next request.

## Kill Switches

```text
Kill Switches
  Feature           State        Fallback message                  Changed            Actions
  All AI features   ● AI on      -                                 <DATE> <TIME>      [Turn off] [Edit]
  Invoice drafting  ● AI off     Drafting help is unavailable...   <DATE> <TIME>      [Turn on]  [Edit]
```

- The global switch is the first row, then one row per AI feature, by its registry display name.
- State is a status badge with its word: on is success, off is warning.
- `Turn off` and `Turn on` are labeled row actions. Each opens a confirmation naming the feature and the consequence, for example that every user gets the fallback path and its message on their next request. The dialog's one solid action carries the verb.
- `Edit` routes to the switch's own page, which holds the fallback message the project confirmed for that feature.
- Only the confirmed owner holds the flip grant. Everyone else who reaches the screen sees the states read-only.
- Every flip is audited and takes effect on the next request, with no deployment and no restart.
- While the global switch is off, every AI admin screen shows one warning banner saying so.

## Credentials On These Screens

- A saved secret shows as a status badge reading `Configured`, with `encrypted at rest` beside it, or `Not configured`, and nothing else. Never any part of the value: no partial value, no last characters, no length hint, and no masked value standing in for the saved one. The same wording holds on every settings screen, per the Settings / config archetype in `.claude/rules/ui-ux-quality.md`.
- A key that authorizes a model call lives on its catalog record, per `catalog-ui.md`. A guardrail or routing screen picks the record by name and never holds a key field of its own.
- A key for a service that is not a model catalog record, for example a voice provider's, sits in System Administration > Integrations with the other non-AI connections, never on a screen in the `AI` group. A model's key stays on its catalog record in AI Providers.
- A key is saved only after a real test call on the current values passes, under the test-before-save gate in `catalog-ui.md`. Blank on edit means unchanged.

## AI Activity And AI Oversight

AI Usage, Guardrail Events, and Quarantine sit in Compliance > AI Activity and are view-only: `View`, and an audited `Export` at most, never an action that changes state. AI Pauses and AI Feedback act on their rows, so they sit in Application Administration > AI Oversight.

### AI Usage

- The dashboard archetype. Metric tiles first: today's calls, tokens, and cost against the budget with the percent used, and the count of degraded runs. A tile whose data failed shows a placeholder, never a zero.
- A per-day chart inside a card, over a date range filter held in the URL. Pair every series with a text label, and offer the same numbers as a table, so the chart is never the only way to read them.
- The speed report from `cost-and-usage.md` as a card-wrapped table: per step and per route, the number of runs, the average time to first text, draft, judge, and total, and the degraded and repaired counts. Pre-drafted runs sit on their own line, out of the averages. The columns sort, the table filters by step, route, and date range, and the state is in the URL.
- Unknown cost reads `Unknown`, never zero.

### Guardrail Events

- The list / index archetype over the verdict rows, with the search and filter bar, sortable columns, the fixed page sizes, and the state in the URL. Default sort: newest first.
- Columns: when, user, feature, layer, verdict, category, mode, and the fail mode applied. Facets: layer, verdict, category, mode, feature, and date range.
- A row for an abusive hit shows its category, the keyed text hash, and the length. Never text. An excerpt shows only for a category that allows one, per What Is Stored in `.claude/rules/ai-guardrails.md`.
- Row actions are labeled: `View`, which routes to the row's detail page. The detail page is read-only too.

### AI Pauses

- In Application Administration > AI Oversight, because `Resume` changes state.
- The list / index archetype over the strike and pause rows. Columns: user, strikes, paused since, ends at in the confirmed reporting time zone, and how it ended. Default sort: active pauses first, then newest.
- Row actions are labeled: `View`, and `Resume` on a user who is paused now.
- `Resume` opens a confirmation naming the user and when the pause would have ended. Resuming closes the pause row as resumed, carrying who resumed it, and is audited.

### AI Feedback

- In Application Administration > AI Oversight, because the reviewer records an outcome. The confirmed queue owner holds a tier that reaches that cluster; where the owner does not, the screen moves to a cluster the owner can reach, never to a widened System Administration and never to Compliance, whose AI screens are view-only.
- The list / index archetype over the feedback rows. Columns: when, feature, rating, coded reason, review status, and the correlation id. Default sort: unreviewed first, then oldest, so nothing waits forever.
- `View` routes to a detail page showing the rated output as text, the reason, and the comment. The reviewer records a coded outcome. A confirmed bad output is marked for the eval set, per User Feedback in `.claude/rules/ai-agent-governance.md`.

### What Every List Here Owes

- An export is an audited action and carries the same fields the screen shows, never more. No abusive text leaves through an export.
- The user column shows the application's own user record. It never shows a decoded token claim.

## AI Output Patterns

What users see around AI output, in any feature. Every pattern is built from existing components, and every model output renders as text.

### Disclosure And The AI Label

- Tell users when they are dealing with AI, where and how the confirmed requirement in `.claude/PROJECT-CONTEXT.md` says. Never assume none applies. When the requirement is unconfirmed, ask, and ship no AI content to users until it is confirmed, per Disclosure Is A Confirmed Fact in `.claude/rules/ai-guardrails.md`.
- Where the confirmed requirement applies, label AI-generated content with one consistent marker: a neutral badge reading `AI generated` beside the AI glyph from the icon registry. The same words and the same glyph everywhere, carried by text, never by color alone.
- The label travels with content that leaves the application, such as an export column, a document's metadata, or an email footer line, per the confirmed requirement. Any machine-readable marking a regulation requires is a confirmed fact.
- An edit by a person does not remove the label unless the confirmed requirement says when it may go.

### Provenance Line

A muted line under each generated item, read from the provenance stored with the item, per `orchestration.md`:

```text
AI generated · <RECORD_NAME> · Improved by review · Pre-drafted · 4.2 s     [ Compare drafts ]
```

- The catalog record's name, never the key, the endpoint, or a decoded claim.
- `Improved by review` when a judge or a critique changed the draft. `Pre-drafted` when a pre-draft was used. A degraded result says `One draft failed` as a warning. A repaired one says `Repaired`.
- The total time for the step.
- `Compare drafts` appears when two drafts exist. It opens a page or a region on the page, never a dialog, showing each draft and the result, with the applied and skipped edits and their reasons. Choosing a different draft happens on that page or region too.

### Streaming And Progressive Rendering

- Streamed text renders as text as it arrives. Only the primary draft streams.
- Partial structured output renders field by field as each field completes, as text, and the final saved result replaces the partial view.
- The image and link policy in `.claude/rules/ai-guardrails.md` applies to every partial render: no image from output, and a link only to an allowed destination.
- The live region announces completion politely, not every chunk, so a screen reader is not flooded.
- In retractable mode, withdrawn text is replaced by a notice, for example `This response was withdrawn because it did not pass a safety check.` Nothing of it stays on the page, in the history, or in the copy action.

### Progress

- While work runs, show a skeleton shaped like the output, or the current step label from the stream's status events, such as `Drafting`, `Reviewing drafts`, or `Checking`. Never a lone spinner in an empty area.
- The one spinner allowed is the small one inside the button in flight, with `aria-busy` set.

### Guardrail Messages In The Conversation

- A blocked message shows as a status line where the reply would have appeared, in the neutral or warning role with its icon and text. It says the message was not processed and why in general terms, and never names the term or pattern that matched.
- A limit message names the limit and when it resets, in the confirmed reporting time zone. A pause message names when the pause ends.
- Content left out of a source shows as an info line saying part of the source was not used.

### Rating And Report A Problem

- Every AI output offers a rating control, keyboard reachable, with an accessible name naming what is rated.
- A negative rating asks for a coded reason from the confirmed reason set, with an optional comment, in an inline region under the output. Never a modal.
- `Report a problem` is a labeled action that opens a page or an inline region for the reason and a description. The server ties the report to the call's correlation id; the user never types it. One toast confirms the report went to review.

### Needs Review

- A step whose upstream step changed after it was accepted shows a `Needs review` warning badge, with the reason naming the upstream change.
- Its content stays as it was. Nothing is deleted or regenerated silently. Re-accepting runs the same accept check as any other acceptance, per `orchestration.md`.

### When AI Is Not Configured Or Switched Off

- The server computes each AI feature's readiness for the requesting user: ready, not configured, switched off, paused for this user, or limit reached. The UI renders from that signal and never infers readiness from missing data.
- An AI action that cannot run renders disabled, with its reason as visible helper text or an accessible description, never a hover-only tooltip.
- An administrator holding the grant for the screen that fixes it gets the reason plus a link to that screen, for example `No model is routed for this step. Set one in AI Routing.`
- Everyone else gets the reason and who to ask, for example `AI drafting is not set up yet. Ask your system administrator.` Never a link to a screen they cannot open.
- A feature switched off shows its confirmed fallback message, and the non-AI path stays usable.

## States Checklist

Every screen here covers all five before it is done:

- [ ] Success.
- [ ] Empty, both flavors: nothing configured or recorded yet, and nothing matching the filter.
- [ ] Loading, as a skeleton mirroring the incoming layout.
- [ ] Error, with a human message and a retry.
- [ ] Small screen, with the tables scrolling horizontally inside their container and the actions column pinned.

And these, where the screen has them:

- [ ] The degraded-check banner, and the global-switch-off banner.
- [ ] Every disabled control with its reason.
- [ ] The discard prompt on a dirty form, from every exit path.

## Icons

Use the one central registry and the same glyph for the same concept everywhere. The concepts these screens add: AI-generated content, a guardrail, a kill switch, a route, and a pause. Register them in the approved style before use, and never add a second icon library. The AI-generated glyph is the same on every screen and every label.

## Do And Do Not

Do:

- Give each AI admin screen its own feature, grant, and sidebar leaf, inside the four fixed clusters.
- Use the Settings / config and list / index archetypes as written, page-hosted, with the discard prompt on every exit.
- Validate every setting on the server, refuse an out-of-range value naming the field, and audit every save with what it was and what it became.
- Pick catalog records by name from active, tested records.
- Show a saved secret only as the `Configured` or `Not configured` status badge.
- Label AI content consistently, show its provenance, and render it as text.
- Give every disabled control its reason, and tell users who to ask when AI is not ready.

Do not:

- Do not put the AI controls on one all-or-nothing page, or let a tab strip stand in for navigation between them.
- Do not drop a dirty form's edits on navigation, and do not use the browser's native `confirm`, `prompt`, or `alert`.
- Do not clamp an out-of-range value silently, or keep runtime state in the settings store.
- Do not show a partial key, its last characters, or a length hint.
- Do not type a model name into a route, or offer an inactive or untested record.
- Do not show the text of an abusive message, or the term that matched, anywhere.
- Do not stream past a check that runs only on the saved copy, or keep withdrawn text anywhere.
- Do not leave a control disabled without its reason, or explain it only in a hover tooltip.
