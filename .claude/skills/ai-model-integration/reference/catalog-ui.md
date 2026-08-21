# Catalog UI

> The footer control called `Test call` here is the same one the UI rule lists as `Test Configuration`:
> it proves the connection and never saves it, so it stays the neutral secondary in every state and the
> footer carries no solid button until `Save` appears.

The screens that manage the catalog. The logic and structure come from this skill; the look and feel comes entirely from the approved design system. Nothing here introduces a style, a token, a color, or a component variant.

Read `.claude/rules/ui-ux-quality.md` for the standard and precedence, and open only the `.claude/skills/ui-ux-design/reference/` files for the components a screen actually uses. Token values come from that skill's `design-tokens.md`, never from memory.

## Two Screens, Existing Archetypes

| Screen | Archetype | Purpose |
| --- | --- | --- |
| Provider list | List / index | Browse, filter, and act on the entries |
| Add / edit provider | Form, with the connection-config test-before-save contract | Author one entry |

No new archetype is created. Same kind of screen, same archetype, per the standard.

There is no run or playground screen by default. Calls come from application code, which is where the prompt is decided; a screen for firing prompts by hand is a diagnostic tool, and the form's own test block already covers "does this entry work". Build one only when the developer asks for it, and ask where it sits rather than placing it.

## Placement And Access

- The catalog is an outbound integration like any other, so it sits as a leaf inside the `Integrations` group in the `System Administration` cluster, beside the project's other connection configuration. Do not give it a group of its own.
- Label it for what it holds. `AI Providers` is the default label; the page title, the breadcrumb, and the back link from the form all use the same words, per the same-concept-same-label rule.
- System-admin only, gated at the handler as well as in the navigation, per `security-and-masking.md`. It is integration configuration holding provider credentials and authorizing spending, which is not the same responsibility as managing the application's own users.
- Never add a cluster for this. The four fixed clusters are a closed set.
- Gate the pages, the row actions, and the query scope, all three, per the defense-in-depth layers in `.claude/rules/ui-ux-quality.md`.

## Provider List (List / Index)

Page header with the primary `Add model` action, the search and filter bar every list carries, a card-wrapped table with sortable columns, and pagination only when there is more than one page. Sort and filter are required here like any other list, per `.claude/rules/ui-ux-quality.md`: default the order to name ascending, and give the bar a search across name and endpoint plus status and last-test-result facets.

```text
AI Providers                                           [ + Add model ]
[ search name or endpoint ]  [ status ▾ ]  [ last test ▾ ]

┌──────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│ Name ▲        Method  Endpoint          Cost in / out   Status  Last tested  Actions                     │
│ ──────────────────────────────────────────────────────────────────────────────────────────────────────── │
│ Claude Opus 5   POST  <endpoint>        $x.xx / $x.xx   Active  6 hours ago  [Edit] [Duplicate] [Delete] │
│ Gemini 3.1 Pro  POST  <endpoint>        Unknown         Draft   never        [Edit] [Duplicate] [Delete] │
│ OpenAI GPT-5.6  POST  <endpoint>        $x.xx / $x.xx   Active  2 days ago   [Edit] [Duplicate] [Delete] │
└──────────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

Columns: name, method pill, endpoint (truncated with the URL cell's ellipsis treatment), cost in and out, status badge, last tested. Name the entry the way someone picking it from a dropdown would recognise it, after the provider and model (`OpenAI GPT-5.6`, `Claude Opus 5`, `Gemini 3.1 Pro`), not after the job it happens to do today (`Primary chat`, `Summarizer`). A job name goes stale the moment the model is used for a second thing, and it hides which provider is being billed. Row actions: `Edit`, `Duplicate`, and `Delete`, each a labeled button carrying its word beside its registry icon at the row size, never a bare icon, per Action Columns Carry Their Labels in `.claude/rules/ui-ux-quality.md`. Three actions fit inline, so none of them goes behind a `More` control.

The model id is not a column. It lives in a body row, because that is where the provider wants it, and promoting it to a column would imply the engine reads it.

Rules:

- Status uses the fixed status roles and nothing else: active is success, draft is neutral, retired is neutral, a failed last test is danger, never tested is warning. Never repurpose a role and never signal by color alone; pair it with text or an icon.
- Show last tested, because an entry that has never passed a test is the thing a reader most needs to notice before depending on it.
- Never render the API key, any part of it, or a length hint, in the list or anywhere else.
- `Duplicate` copies the entry as a new draft, with no key carried over and `last_test_status` reset to untested. Cloning is the fastest safe way to add a model variant. Like every control in the actions column it is a labeled button in the neutral secondary look, never an unlabeled icon and never the solid one.
- Delete is a soft delete to the recycle bin with a worded confirmation. The row's `Delete` only opens that confirmation, so it keeps the neutral secondary shell and takes the danger color on its icon and label alone; the dialog's `Move to Recycle Bin` is the solid danger action that commits. Only a system admin permanently deletes.
- Cost renders each price with the project's currency symbol, and renders `Unknown`, not zero and not a bare dash, when the price is unset. See `cost-and-usage.md`.

States: no-data-yet offers `Add model`; no-results after a filter offers clear-filters; loading is a table skeleton keeping the header row; a load failure shows the error state with retry, never an empty table.

## Add / Edit Provider (Form, Test Before Save)

One shared template for both modes, keyed on whether the record exists. Back link, section cards, then the footer. Errors report once, where they are: inline under each field, with a blocked submit announced by one error toast and focus moved to the first invalid field, per the validation section of `ui-ux-quality.md`. No summary card.

Seven section cards, in order:

1. **Identity**: name, the masked API key, and status.
2. **Endpoint**: method and URL.
3. **Headers**: the row repeater, key and value.
4. **Body**: the typed row repeater, type, key, and value.
5. **Response paths**: content path, error path, usage in, usage out, finish reason.
6. **Cost**: price in and price out, each carrying the currency symbol.
7. **Test**: the prompt used for the test call, then the result panel.

There is no credential section: the key is a field in Identity. There is no routing section: the timeout is one project-wide value and there is no fallback. There is no version badge, no currency control, and no price-confirmed date. Each of those was a field that asked the user for something the system already knew or did not use.

### One Section, One Row

Every field in a section sits on a single row. A section does not wrap into a second row of fields.

```text
1  Identity
   Name                    API key                 Status
   [ OpenAI GPT-5.6      ]  [ ••••••••••••••••••• ] [ Active     v ]

2  Endpoint
   Method                  URL
   [ POST             v ]  [                                      ]

6  Cost
   Price in (per 1M)       Price out (per 1M)
   [ $                  ]  [ $                                    ]
```

- Declare the column count per section rather than letting a two-column grid wrap. A section with five fields is a five-column row.
- A full-width cell that is not a field, a help note or a status line, may take its own row underneath.
- Step the column count down on narrower screens, and go single-column on small ones. A five-column row is unreadable on a laptop at full width.
- Never leave half a row empty by putting a full-width field after a lone half-width one.

### Field Population

- **Add**: every field is empty and shows a placeholder hint. Nothing is pre-filled, so nothing on screen can be mistaken for saved configuration.
- **Edit**: fields show the saved values, with one exception. The API key is always blank, never re-rendered, and blank on save means unchanged rather than cleared.

### The Test-Before-Save Gate

This is connection configuration, so the test-before-save contract in `.claude/rules/ui-ux-quality.md` applies in full and is not optional here:

| State | Footer buttons | Look |
| --- | --- | --- |
| Untested, or any tested field edited since | `Reset` + `Test call` | both neutral secondary, so the footer carries no solid button |
| Test in progress | `Reset` disabled + `Test call` loading | unchanged; loading swaps the label for a spinner at the same width |
| Test passed, values unchanged | `Reset` + `Test call` + `Save` | `Reset` and `Test call` unchanged, `Save` the one solid action |
| Test failed | `Reset` + `Test call` | as untested, with the failure shown inline |

`Reset` and `Test call` are the same neutral secondary look in every state, per Two Button Looks, Everywhere in `.claude/rules/ui-ux-quality.md` and `.claude/skills/ui-ux-design/reference/buttons.md`. Neither is ever the solid one: `Reset` discards edits and `Test call` proves the connection rather than saving it. So `Save` is the only solid button this footer ever has, and it exists only after a pass.

- `Test call` sends one real call using the fields currently on screen, saving nothing. That is what makes an unsaved edit testable.
- On the edit screen the key field is blank, so a test with a blank key uses the saved key. This is what lets Test work on an edit without retyping the key.
- Editing any tested field after a pass clears the result back to untested and hides or disables `Save`, which leaves the footer with no solid button again. `Reset` and `Test call` look exactly as they did before the pass. Editing a header or body row counts.
- There is no save-anyway, no skip-test, and no force-save. An entry that has never made a successful call must not become callable.

> [!WARNING]
> A saved but untested entry is a call that fails in production at the moment a user needs it, having already been marked configured. The gate is the whole reason the pattern is safe to hand to someone who is not the person who wrote the engine.

## Component Contract - Typed Row Repeater

The Key, Value editor for headers, and the Type, Key, Value editor for body fields. This is the spreadsheet-style row editor from the builder archetype, applied to a form section.

```text
Body
┌───────────┬───────────────────┬──────────────────────────────────────┬────┐
│ Type      │ Key               │ Value                                │    │
├───────────┼───────────────────┼──────────────────────────────────────┼────┤
│ [Text  ▾] │ model             │ <MODEL_ID>                           │ ✕  │
│ [Number▾] │ max_tokens        │ 1024                                 │ ✕  │
│ [JSON  ▾] │ messages          │ [{"role":"user","content":"{{prom... │ ✕  │
└───────────┴───────────────────┴──────────────────────────────────────┴────┘
                                                            [ + Add row ]
```

- A legend row labels the columns. Each column is a real labelled control, not a placeholder standing in for a label.
- The type control offers exactly the four types, with plain labels: Text, Number, True/False, JSON.
- One remove control per row. This is the sanctioned icon-only case, because a repeater row is a field group rather than a record's actions column and the legend row already says what the column is, so it is chrome (`icon-btn`) with an accessible name naming what it removes, and reachable by keyboard.
- `Add row` appends an empty row and moves focus into its first field. Rows added by script and rows rendered by the server are identical in markup, so one set of behaviors covers both.
- Removing the last row leaves the header legend and the add control visible, never a bare empty area.
- A `json` row validates on blur and shows the parse error under that row, in the reserved message slot, per the validation contract. Validation is error-only, and the error clears on input once fixed. Probe the value with placeholders resolved, matching the quoting rule in `placeholders.md`, so a correctly authored row does not report a false error.
- A row with a blank key is ignored on save rather than rejected, matching the engine.
- Show where the placeholders go: `{{api_key}}` in a header value, `{{prompt}}` in the body value that carries the prompt. A one-line hint under each repeater is enough.
- On small screens the row collapses to a stacked group per row with its labels shown, rather than a horizontally scrolling grid.

## Component Contract - Call Result Panel

One panel renders every call result, so a reader learns it once.

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│ ● Success (HTTP 200)                                    1.8s · 412 tokens    │
├──────────────────────────────────────────────────────────────────────────────┤
│ Response                                                                     │
│ <the value at content_path, as text>                                         │
├──────────────────────────────────────────────────────────────────────────────┤
│ Usage and cost   in 128 · out 284 · cost $x.xxxxxx                           │
├──────────────────────────────────────────────────────────────────────────────┤
│ ▸ Request sent (masked)                                                      │
│ ▸ Raw response                                                               │
└──────────────────────────────────────────────────────────────────────────────┘
```

Sections, in order: a status bar, the response, usage and cost, the masked request, the raw response.

- The status bar carries the outcome as a status badge with text, never color alone, plus the HTTP status and duration.
- Label the panel by the entry's name. Never by the key field, which is a secret.
- The response section renders only when the content path hit. All provider text is rendered as text, never as markup. This is a hard requirement, not a preference.
- Usage and cost render only when at least one is known, and render unknown as unknown.
- The masked request and the raw response are collapsible monospace blocks with a bounded height and their own scroll. Collapsed by default on success, expanded by default on failure or a path miss.
- A path miss is its own visible outcome, not a blank response section: state that the call succeeded and the content path matched nothing, name the path, and expand the raw response so the correct path can be read off it and fixed in one edit.
- On failure, show the provider's message from the error path, expanded, with no stack trace and no internals.
- Loading is a skeleton shaped like this panel, not a spinner in an empty box. The one permitted spinner is the small one inside the button in flight.
- Announce the outcome through the toast layer and an `aria-live` region: polite for success, assertive for an error. The button never becomes a success checkmark.

## States Checklist

Every screen here covers all five before it is done:

- [ ] Success.
- [ ] Empty, both flavors: nothing configured yet, and nothing matching the filter.
- [ ] Loading, as a skeleton mirroring the incoming layout.
- [ ] Error, with a human message and a retry.
- [ ] Small screen, with the repeater stacked and the tables scrolling horizontally inside their container.

## Icons

Use the one central registry and the same glyph for the same concept everywhere. The concepts these screens add: the AI provider or catalog entry, and a raw structured payload. Register them in the approved style before use, and never add a second icon library.

## Do And Do Not

Do:

- Use the existing archetypes, tokens, and components exactly as written.
- Put the catalog inside the Integrations group, system-admin only, and label it the same way everywhere.
- Keep every field in a section on one row.
- Apply the test-before-save gate in full, including invalidating on edit.
- Render provider output as text.
- Show cost and last-tested state where someone deciding to call needs them.
- Report a path miss as a path miss, with the raw response open.
- Give every row action its word beside its icon, and take both button looks from the button standard as written.

Do not:

- Do not invent a layout, a status color, a component variant, or a new archetype for these screens.
- Do not give the catalog its own navigation group, or put it outside the four fixed clusters.
- Do not build a run screen unless the developer asks, and do not place one without asking where it goes.
- Do not render a saved key, a partial value, or a length hint anywhere, including in a result panel label.
- Do not pre-fill an add form, and do not re-render a saved key into an edit form.
- Do not offer a save path around a failed or missing test.
- Do not build the result panel by concatenating provider text into markup.
- Do not show a lone spinner where the panel or the table has a skeleton shape.
- Do not ship a bare icon in the actions column, and do not change a button's look because its state changed, including promoting `Test call` to the solid one.
