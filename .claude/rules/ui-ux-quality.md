# UI Layout And Quality Rules

The team's authoritative UI standard. Every application's interface follows the same layout architecture, design-token system, page archetypes, and role-aware navigation so all apps look and behave consistently.

Conditional: applies only when the project includes a user interface. It does not override `.claude/rules/secret-handling.md`, `.claude/rules/schema-mcp.md`, `.claude/rules/production-readiness.md`, or the DDL restrictions.

## Authority And Precedence

- This is the high-priority baseline for all UI work. Where it specifies a structure (shell, navigation surface, archetypes), follow it.
- Stack-agnostic: translate the patterns into the project's confirmed UI stack rather than copying one stack's idioms.
- The theme is approved, not per-app. Design tokens, brand palette, per-theme surfaces, fonts, logos, favicons, and icon style are approved constants in the bundled `ui-ux-design` skill (`.claude/skills/ui-ux-design/`), every value in `reference/design-tokens.md`. Use them by default; do not re-derive, restyle, or re-skin. Every application reproduces the same look and feel.
- The concrete design system is the bundled `ui-ux-design` skill: written component guidelines plus the token specification and brand asset pack. Claude implements those guidelines in the confirmed stack; the skill supplies guidelines and image assets, not code to copy.

## Apply This Standard Exactly (non-negotiable)

Apply it exactly, the same way every time; do not reinterpret, rename, or invent your own variant. Deviate only when the developer explicitly asks, recorded as a documented exception in `.claude/PROJECT-CONTEXT.md`, never silently. Consistency holds only because every project follows these identical rules deterministically.

Apply exactly, every time: the shell and navigation architecture and its behaviors; the page archetypes (same kind of screen -> same archetype); the component primitives and their interaction contracts; the two button looks (one solid action per group, one neutral secondary for every other labeled action) and the labeled actions column; the status roles and their meaning (success = healthy/confirmed, warning = pending/attention, danger = destructive/error, info = informational, neutral = draft) - never repurpose a role or use an ad-hoc color; the iconography (same glyph always denotes the same concept/action); the role/access tiers; and the state-vocabulary -> status-role mapping plus the interaction, empty/loading/error, and accessibility conventions in this file.

Only the declared App Definition varies: the app-specific values in this project's `.claude/PROJECT-CONTEXT.md` (app name, title-bar name, navigation tree, brand-assets destination path, UI stack, entities, roles, feature toggles, domain copy). These change what data the UI shows and what it is called, never how it looks, behaves, or what anything means.

## Identity And Brand Assets - Approved Pack Plus App-Specific Names

Approved identity assets (never vary, never substitute) ship as image files under `.claude/skills/ui-ux-design/assets/` and are used as-is:

- Company name: CLaaS2SaaS
- Logo, per theme: `logo-full-light.png` / `logo-full-dark.png` (expanded sidebar) and C2S short marks `logo-short-light.png` / `logo-short-dark.png` (collapsed rail); light variants on light chrome, dark (white) variants on dark chrome.
- Favicon, per theme: `favicon-light.ico` / `favicon-dark.ico` (multi-size), swapped with the effective theme.
- Icon style: one central inline-SVG icon registry to one approved style (24px viewBox, 2px stroke, round caps/joins, outline); extend it in the same style, never add a second icon library.

Never recolour, box, pad, plate, stretch, regenerate, or replace these assets.

Asset placement is a developer decision (ENFORCED, ask, never assume): where the files live inside the app depends on the confirmed stack (for example `public/`, `wwwroot/`, `static/`), recorded as `<BRAND_ASSETS_PATH>` in the App Definition. If the path is recorded, copy the files there (or confirm they are present) and reference them from it. If not recorded, stop and ask the developer where the files go; record the confirmed path before generating UI that references the assets.

Still supplied by the developer and recorded in the App Definition (obtain from there or ask; never invent, generate, randomly pick, or silently default): the app name; the browser/document title-bar name (`<title>`); the sidebar menu structure (features, groups, leaves populating the four fixed clusters); and `<BRAND_ASSETS_PATH>`. If any is missing, stop and ask before producing UI that displays or depends on it.

## Shell And Navigation Architecture (mandatory)

- Every authenticated screen extends one master shell layout with three regions: a left sidebar, a slim top bar, and the main content area.
- Layout (ENFORCED): the sidebar is full height and owns the top-left corner; the top bar spans only the main column (over the canvas), never full-width above the sidebar. Render a visible divider between the three regions, and make the brand block (rail head) the same height as the top bar so their bottom dividers form one continuous line at every breakpoint.
- All standing navigation lives in the left sidebar, config-driven (clusters -> features, where a feature is a leaf link or an accordion group of child features), not hand-coded per page. The top tier is the cluster, a heading, not an accordion group. The clusters are approved constants rendered top-to-bottom in this fixed order, each with a defined home: `Workspace` (the day-to-day working area, the app's primary work features), `Compliance` (audit logs, activity trails, compliance/governance views), `Application Administration` (the application's own users, roles, and app-level settings), `System Administration` (system-level configuration, integrations, platform settings). Place every feature under the one cluster whose purpose it fits. The four are a closed set: an app uses only the clusters it needs and omits the rest (an unused or empty cluster is not rendered). Never add, invent, rename, or reorder a cluster. Accordion groups nest at most three levels deep within a cluster (the cluster heading and a leaf do not count). A leaf under the third-level group that still needs children does not force a fourth accordion level: it becomes a routable page whose children render as a horizontal in-canvas tab strip (see Tabs), never a deeper accordion (a level-3 group whose children are plain leaves stays an accordion). Never name a group the same as its cluster; name groups for what they hold.
- Role-filtered: filter, don't fork. Render the tree once and drop features the user's roles cannot access; a group disappears when all its children are filtered out, and a cluster disappears when it has no visible features. Never maintain per-role duplicate menus.
- Accordion groups expand/collapse in place; the group holding the active route auto-expands and shows the active-trail tint, and each group's open/closed state persists across navigation. Every node (leaf and group) carries a meaningful, mandatory icon from the approved registry. Not-yet-built destinations render disabled with a "Soon" indicator.
- The brand block displays the company logo image (not the app-name text) in a reserved rectangular area: the wide logo when expanded, the C2S short mark when collapsed, per-theme variants swapped with the effective theme. Keep the logo as supplied at its natural size, directly on the chrome; never recolour, box, pad, plate, or shrink it.
- Pinned rail head and scroll (ENFORCED): the brand block (and the sidebar nav filter, when enabled) stays fixed; only the nav list scrolls, so the logo/filter never scroll out of view. The nav filter matches both leaf and group/accordion labels (a group-name match reveals the group and its children).
- Collapse / expand behavior (ENFORCED):
  - Expanded, the sidebar shows the wide logo and collapses to an icon-only rail via a collapse control in the brand block. That control uses one icon, the panel/sidebar-toggle glyph (`i-panel`), the same in both states.
  - Collapsed, the short mark and the expander share one slot and cross-fade: at rest show only the short mark; on hover OR keyboard-focus of the brand block show only the `i-panel` expander on an opaque overlay covering the slot exactly. Activating the block (click / `Enter` / `Space`) re-expands the rail, and the collapsed state persists. The brand block is the only top control (home link, and collapsed it is the expander). Define the overlay's surface/foreground in both themes.
  - In icon-rail mode, hovering or keyboard-focusing a group reveals its children in a flyout (leaves show a label tooltip). The flyout stays as legible as the expanded rail: give every entry (nested group titles and leaves) its icon and indent nested groups so the hierarchy reads, and set the flyout's own header apart. The flyout/tooltip must escape the scrolling rail's clip (fixed or portaled), use hover-intent (a short close delay, stays open while the popover is hovered), and route through the same in-app navigation (never a raw link that reloads or leaves the app). A small chevron hint may sit on the group icon (not the collapse toggle).
  - Keep the same collapsible rail at every breakpoint (the logo stays visible). An off-canvas drawer is optional for very small screens (slide-in with a dimmed backdrop); if used, still surface the logo (for example in the top bar), never a bare hamburger.
  - Flicker-free collapse/expand (ENFORCED): no flash, clip, wipe-in, pop, hard-cut, or vertical jump in either direction while the rail width animates. Use an asymmetric delay, not just clipping:
    - Hide expanded-only content (labels, group chevrons, "Soon" pills, wide logo, collapse control) by opacity while it stays in flow, never by `position:absolute`/`width:0` (whose out-of-flow snap is itself the flash).
    - Fade OUT fast with zero delay on collapse; fade IN only after the width animation has nearly finished on expand (a `transition-delay` about the width duration). Put the delay on the expanded-state selector and zero delay on the collapsed-state selector, so the same rules drive both directions. Tune the expand delay just under the width duration.
    - Keep each nav icon at a consistent inset so it does not slide; do not re-center the item when collapsed.
    - Make the brand logos absolute overlays that cross-fade at fixed positions (reserving no layout box), so the collapsed rail stays centered and the mark never slides. Let the collapse control ride just inside the moving edge (never clipped) and fade with the panel's easing over a duration close to the width animation.
    - Collapse vertical regions (nav filter, cluster labels, an open group's body) with `max-height` coupled to the width, so vertical reflow rides with the horizontal animation.
    - Define any state-driven overlay unconditionally but invisible so it fades rather than hard-cuts; drop hidden controls from the tab order with `visibility: hidden`/`disabled` (opacity alone leaves a focus stop); honor `prefers-reduced-motion` by making the change instant.
- The top bar is slim, sticky, carries no navigation tabs, and has no global search bar. It shows the application name on the left, then utilities on the right (notifications, profile menu) and no action buttons (a primary action such as a create/"New" button belongs in the page header of the relevant archetype). Standing navigation never moves to the top bar. The theme switcher is low-frequency and may live inside the profile menu; present its options as one connected horizontal segmented control filling the menu width in equal segments split by thin dividers (System / Dark / Light in that left-to-right order, one icon segment per option, active one highlighted, each with an accessible name), not detached buttons and not a vertical list, defaulting to System, in a fixed profile-menu section labeled Appearance with a leading icon matching the other rows.
- The profile menu (from the top-bar avatar) leads with an identity block (avatar beside the user's name, email below) rendered as a single clickable row with a trailing chevron that opens the profile page, then the fixed Appearance section (the theme switcher), then sign out. The identity block carries the name/email; do not add a separate header.
- Authentication screens (sign in / register / reset) do not use the shell: standalone, centered cards sharing only the brand mark, tokens, and font.

In-canvas tabs are a sanctioned navigation surface bounded to two roles: (1) overflow for navigation depth beyond the sidebar's three accordion levels (a leaf under a level-3 group that needs children renders those children as a horizontal tab strip, not a fourth accordion level); and (2) switching between a single record's or page's facets. The tab strip sits at the top of the work canvas, each tab is a deep-linkable route reflected in the breadcrumb, and tabs are terminal (a panel holds content, never another tab strip and never a fresh sidebar accordion). Standing navigation never moves to the top bar, and tabs never replace the sidebar. A list's compact filter control (narrows rows, reads as a filter, not navigation) is a separate affordance. See Tabs under Component And Interaction Patterns.

## Design System And Tokens (fixed, mandatory)

- Build the UI from the approved design system; `.claude/skills/ui-ux-design/reference/design-tokens.md` is the single source of truth for every value in both themes. Implement values exactly as written: token names may adapt to the stack, values may not, nothing is re-derived per app.
- Approved brand palette (CLaaS2SaaS Brand Guidelines): primary accent Midnight Blue `#193E6B`; active-nav gold Green Gold `#B3A125`; success Avocado Green `#5F8025`, warning Sunray `#E9AC53`, danger Violet-Red `#991547`, info Jelly Bean Blue `#448E9D`, secondary Cadmium Violet `#7F3F98`. Never repurpose a role's color or introduce an off-palette hue (a new tint derives from an approved hue). Status color is never the only signal; always pair it with text or an icon.
- Fixed surfaces, per theme (the two chromes are different colors by design): light = white chrome (`#FFFFFF`) over a warm platinum canvas (`#E8DFD0`) with white cards; dark = ink-navy chrome (`#080F1A`) under a lifted navy canvas (`#1A2E46`) with raised navy cards (`#253E5D`). Text navy ink (`#1E2E42` light / `#E9EFF6` dark), muted `#4D5E75` / `#A3B2C5`. All pairs are WCAG AA verified; semantic colors used as text or icons go through the theme-aware readable tokens, never raw semantic hex on a surface.
- Fixed type and shape: Montserrat headings + Source Sans 3 body; the 4px spacing scale; radii 12px cards / 8px controls; flat resting cards (border-defined) with soft elevation reserved for overlays and the chrome edge shadow.
- Two distinct surfaces: the chrome (sidebar + top bar) shares one surface color; the main canvas uses a clearly distinct surface (which is darker is per-theme). Render the divider and chrome edge shadow so the boundary is unmissable.
- Provide the theme switcher in the profile menu's fixed Appearance section (System / Dark / Light, always on), persist the choice, define every token in both themes, declare `color-scheme` per theme, and swap logos and favicon with the effective theme.
- Render icons from one central icon registry built to the fixed style (inline SVG, 24px viewBox, 2px stroke, round caps/joins, outline); register before use, extend in the same style, keep icons meaningful.

## Page Archetypes (mandatory selection)

Pick the matching archetype and fill it with the entity's data; do not invent a new layout per screen:

- Dashboard: optional accent hero, a metric-tile grid (harden each count so a data failure shows a placeholder), then a quick-actions grid or recent-activity table; optional user-arrangeable, per-user-persisted widget grid when enabled.
- List / index: page header with primary CTA, a search and filter bar, a card-wrapped table with sortable columns and a declared default sort, an actions column whose every control carries its own label, and a pagination footer only when there is more than one page. Sorting and filtering are required here, not optional extras. See Every List Sorts And Filters and Action Columns Carry Their Labels below.
- Detail / show: a breadcrumb line whose parent segment is the way back to the list, a header card (title + status badge + meta line + right-aligned actions), then a 2/3 + 1/3 body grid where the side panel widens to full width when its sibling is absent.
- Form (create / edit): one shared template for both modes (keyed on whether the record exists): a breadcrumb line whose parent segment is the way back to the list, section cards of fields, and a footer with a neutral secondary `Cancel` beside the one solid submit; always repopulate prior input. Errors report inline per field, with a blocked submit announced once by a toast, per Validation Reports Once, Where The Error Is below. Data entry lives in the app's own UI, not in a popup (ENFORCED): a create or edit form is either its own route or a form region on the current page, inside the shell, and never a modal dialog. See Data Entry Is Page-Hosted below.
- Step-by-step form (multi-step / wizard): one step's fields at a time under a step indicator, page-hosted like any other form with the same breadcrumb line, with exactly two footer controls, `Back` and `Continue`. It follows a stricter, mandatory sub-pattern (ENFORCED): `Continue` validates the current step, saves the entered values as a draft, and only then advances, so nothing already entered depends on the browser staying open. See Step-By-Step Form Drafts below for the full contract.
- Settings / config: list + form, with boolean toggles, secrets shown only as a masked badge labeled "encrypted at rest", structured config in a monospace area, and optional row-level test/health actions. Connection / integration configuration (API credentials, email/SMTP, third-party app connections, webhooks, or any other external-service config) follows a stricter, mandatory sub-pattern (ENFORCED): the form footer shows `Reset` and `Test Configuration` only; there is no `Save` action until `Test Configuration` returns a successful connection. `Reset` restores the last-saved values (or clears the form on first setup) and discards unsaved edits. `Test Configuration` runs the real connection check as an async action per Buttons And Async Actions (disable + loading affordance, guard against double-submit) and reports the outcome via toast plus an inline connection-status indicator. `Save` appears (or becomes enabled) only after the most recent `Test Configuration` on the current field values succeeded; edit any tested field afterward and `Save` hides/disables again and a new successful test is required before saving. Never offer a way to save unvalidated connection settings, including a "save anyway" or force-save affordance.
- Builder (hub-and-spoke): a central hub page whose row actions spoke out to single-purpose sub-pages, each returning via a back link; inline lifecycle state with exactly one confirm-guarded "advance" control; a validation gate before transitions.
- Auth: a standalone centered card (no shell), brand mark, optional SSO button, flash/error display, and a trust footer.
- Recycle bin / soft-delete: destructive deletes route here, not hard delete; per-user restore; admin buckets by entity with restore + confirm-gated permanent delete; "empty everything" requires a type-to-confirm word.
- Status / result: a single full-width card tinted by outcome with a circular icon medallion, a bold headline, and a muted explanation.

A record's facets are navigated with the horizontal tab strip under Component And Interaction Patterns (one tab per facet, each a deep-linkable route).

An AI provider catalog is not a new archetype. It is two screens: list / index for the entries, and form plus the connection-configuration test-before-save sub-pattern for the add/edit screen. It sits as a leaf inside the `Integrations` group under `System Administration`, admin-only, never in a group of its own, and every field in a form section sits on one row. Build a run/playground screen only when the developer asks, and ask where it goes. The full screen, repeater, and result-panel contract is in `.claude/skills/ai-model-integration/reference/catalog-ui.md`, governed by `.claude/rules/ai-model-catalog.md`.

## Data Entry Is Page-Hosted (mandatory)

A form that creates or edits a record is part of the application's own UI, not a dialog stacked over it. Build it as its own route (`.../new`, `.../<id>/edit`) or as a form region on the current page, inside the shell, with the page header, breadcrumb, and standing navigation all still there. A popup is not an option for data entry, and neither is a drawer or an off-canvas panel standing in for one.

- Never put a create form, an edit form, a multi-step form, or a settings form in a modal, however few fields it has. Same-page or new page, and nothing else.
- A modal stays what it is for: a decision that must be answered now. That is the confirmations under Component And Interaction Patterns, plus a short result or information dialog.
- A decision dialog may carry up to about three fields when those fields are the decision itself: the word typed to confirm a purge, a reason for a rejection, a new date when rescheduling. That is a decision with an input, not a record editor. Anything beyond it is a page.
- Row-level editing in a table stays inline for a single cell. For anything wider, route to the record's edit page rather than opening a dialog.
- Do not compensate for the removed popup by cramming the form into a cramped panel. A page has room, so use the archetype's section cards and let long content scroll the page normally.

## Step-By-Step Form Drafts (mandatory)

A multi-step form holds work the user has already done. Keeping that work only in the browser means a closed tab, an expired session, a dead battery, a dropped connection, or a switch to another machine throws it away and the user starts at step one. So every step-by-step form that creates or edits a record persists a resumable draft. This is not a per-screen decision.

- Two controls, `Back` and `Continue`. `Continue` validates the current step, persists the entered values as a draft, and advances only after the save succeeds. On the last step the same control becomes the completion action (`Create` / `Save` / `Finish`) and commits the record. Never add a third `Save as draft` button: `Continue` is the save.
- The draft is stored server-side, owned by the user who entered it, and carries the step that was reached. Browser storage may cache it, but a draft that exists only in the browser does not satisfy this rule, because it does not survive the device.
- Authorize a draft like any other record, through the same layers under Role And Access Model: the per-record policy and the list/query scope both hold it to its owner, so nobody can resume, read, or discard someone else's half-finished work by guessing an id.
- On completion the draft stops being a draft. Close it out in the same transaction that commits the record, so the finished record appears once, the draft disappears from the drafts surface, and nobody resumes a flow that already ended.
- Resume, do not restart. The draft is reachable from a standing surface (the entity list with a `Draft` status badge in the neutral role plus a `Continue` row action, or a drafts view), and re-entering the create flow offers the saved draft rather than opening blank. Resuming reopens the step that was reached with every earlier step restored and marked done, and states in one line what was restored.
- A failed save is the case this pattern exists for. Do not advance, do not clear the fields, keep every entered value in the form, and surface a retryable error per Buttons And Async Actions. Losing the step because the save failed is the exact defect the draft was meant to prevent.
- Confirm the save quietly. A per-step toast is noise, so use a muted saved-at line in the form footer and announce it politely through the live region; reserve a toast for a failed save or an explicit save-and-close. This is the one sanctioned exception to confirming an async action with a toast.
- Discarding a draft is explicit and confirm-guarded, and the confirmation names what is lost. A draft never became a record, so it does not route to the recycle bin and the soft-delete default does not apply to it. The guard on leaving mid-step says plainly that the saved steps are kept and only the current step's unsaved edits go, rather than implying everything is lost or that everything is safe.
- A draft never holds a secret. Exclude credential, API key, token, and password fields from the persisted draft, per `.claude/rules/secret-handling.md`, and re-collect them on resume. A draft also never satisfies the connection-configuration test-before-save gate above.
- Storage shape is the developer's decision, recorded in `.claude/PROJECT-CONTEXT.md`: a dedicated draft table, or the record's own table with a draft state. Either way a draft is not a record. It may appear as a row in its own owner's list view, carrying the `Draft` badge and the `Continue` action, and it stays out of the default-filtered result and its counts until the owner asks for drafts, and out of every other list, export, metric tile, and report. When the drafts filter is on, the draft rows on screen are counted in that filtered total like any other match, because a count that disagrees with the rows beneath it reads as a bug. Ask which shape the project wants, and name the trade-off: a draft in the live table needs its required columns relaxed until completion, which weakens them for committed rows too, while a separate table keeps the live constraints strict.
- Draft storage is persisted data like any other. Take it through gates D and SEM: verify names live, run the reuse-first search, declare the grain, keep the draft state a codified member rather than free text, and confirm how long an abandoned draft is kept per `.claude/rules/data-governance.md`.
- Autosaving more often than each advance (on an interval, on blur, on close) is welcome and stays the developer's call. Saving on every advance is the floor, not the ceiling, and extra autosave never relaxes the step validation that gates advancing.

## Validation Reports Once, Where The Error Is (mandatory)

A form tells the user what is wrong in one place: under the field that is wrong. A card at the top of
the form listing the same sentences again does not add information, it doubles it - the user reads
"Enter a subject" in the summary, reads it again under the field, and now has two places to check as
they work. So there is no error-summary card. What a summary was really doing was announcing that the
submit failed, and that is a toast's job.

- **Every field error is inline**, directly below its field, tied with `aria-describedby`, in the
  reserved message slot so the layout does not jump. That message is the record of what is wrong, and it
  stays until the field is fixed.
- **A blocked submit is announced once**, by one error toast that says how many fields need attention,
  while focus and scroll move to the first invalid field. An error toast persists until dismissed, so
  nothing is lost by not repeating it in the page.
- **No summary card, and no list of field errors anywhere but the fields.** Not at the top of the form,
  not at the foot, not in a banner.
- **A form-level error that belongs to no field** - the submit itself failed, a cross-field rule broke,
  the service was unreachable - gets one persistent inline alert at the form foot, beside the submit,
  because the user needs the reason in front of them while they act on it. One message about the form,
  never a list of the fields.
- **A server or async error that does belong to a field** is mapped back onto that field like any other
  inline error, not left in the form-level alert.
- The timing contract is unchanged: validate on blur, re-validate on input once a field has errored,
  keep the submit enabled, and never decorate a valid field.

## Every List Sorts And Filters (mandatory)

A list the user cannot reorder or narrow has to be read line by line, and it gets worse every week the data grows. Every list / index screen ships both. Neither is a per-screen decision, an advanced tier, or something to add later.

- Sortable columns: every column holding a value worth ordering sorts, which is most of them (name, code, status, owner, dates, counts, amounts). The actions column and free-text notes are the exceptions. One column sorts at a time, the header is a real button carrying `aria-sort`, and direction is never signalled by the caret alone.
- Declare the default sort for every list, and say what it is. A list with no stated order opens in whatever order the database happened to return, which quietly changes under the user.
- A search and filter bar above every list, built from the approved search-filter component: free text across the fields named in the placeholder, plus one facet per dimension the list is genuinely narrowed by (status, type, owner, source, date range). Facets are the codified reference sets behind those columns, never free-text matching, per `.claude/rules/semantic-data-model.md`.
- Sort and filter apply to the whole result set, never only the rows already loaded. Where the list is paginated, the server sorts and filters and returns the page already narrowed. Reordering the twenty-five rows on screen while the rest of the matches sit behind pagination is a defect, not a simplification.
- Changing a filter or the sort returns to page one, and the count and the pagination total both report the filtered total, so "Showing 1-25 of 47" means 47 matches rather than 247 rows.
- Sort and filter state lives in the URL query, so a refresh, the back button, a bookmark, and a link pasted to a colleague all reproduce the same view. Restoring a view is reading the URL, not remembering client state.
- A filter matching nothing is the no-results state with a clear-filters escape, never the no-data-yet state and never a blank body. Announce the new count politely through the live region.
- Keep it proportionate: a short read-only sub-table inside a detail page, and the dashboard's recent-activity table, need neither control. This applies to the list / index archetype, where the user goes to find a record.

## Two Button Looks, Everywhere (mandatory)

A button that means the same thing on two screens has to look the same on both. When one screen renders
`Cancel` as a filled button and the next renders `Clear` as a borderless one, the user re-learns the
interface page by page, and nothing reliably marks which control is the safe exit. So a button's emphasis
is not a per-screen judgement: there are two looks, and which one a control gets is decided by the rule
below, identically, every time.

- **Solid, exactly one per action group.** The group's one main action, in its meaning-driven variant
  (primary for a save or a create, danger for the delete that actually commits, success for a positive
  completion, warning for a non-destructive override, accent for a highlight or upsell). Never two solid
  buttons in one group.
- **An action group is one cluster of controls the user chooses between in one place:** a form footer, a
  dialog footer, a page header's action cluster, a row's actions cell, a table toolbar, an empty state's
  action row. A page is not a group, so a page-header CTA and a failed region's `Retry` are both solid
  without competing. Inside one cluster: one solid, or none.
- **Neutral secondary, everything else.** Every other labeled action, on every screen, in one shared
  neutral treatment: surface fill, one control border, ink text. Only the size changes with context.
- **Same geometry across both looks.** One height, padding, radius, font, weight, icon size, and
  icon-label gap per size step. Only the fill, the border, and the label color differ. Never resize or
  re-round a single button to make it fit a layout.
- **These are never the solid one, whatever the screen:** `Cancel`, `Back`, `Close`, `Dismiss`, `Skip`,
  `Keep editing`, `Clear`, `Clear all`, `Clear filters`, `Reset`, `Reset filters`, `Apply`, `Filters`,
  `Export`, `Columns`, `Density`, `Duplicate`, `Test Configuration` (also written `Test call`), and every
  actions-column control.
- **A group that only changes what is on screen has no solid button at all** - a filter bar, a filter
  popover footer, a toolbar of view controls. Nothing in it commits anything, so nothing in it earns the
  one solid; the page's solid action stays the archetype's primary CTA in the page header. `Test
  Configuration` is on that list for the same reason, since it proves a connection rather than saving it,
  which is why a connection form shows no solid button until the test passes and `Save` appears.
- **A button's look never changes with state.** Loading swaps the label for a spinner at the same width
  and changes nothing else. Never promote a control from secondary to solid, or demote it, because its
  state changed; a button that shifts emphasis between two states is the same defect as two screens
  disagreeing, only closer together.
- **A status meaning colors the label, not the shell.** A destructive secondary action (a row's `Delete`)
  keeps the identical neutral shell and takes the theme-aware status foreground on its icon and label
  only, never a different fill, border, height, or radius.
- **Icon-only is chrome, not a button, and the list is closed by kind:** a dismiss or close mark on
  something dismissable (a modal, a toast, a flash), a clear mark on a field or a chip (a search input, a
  filter field, a filter chip), and a form repeater's per-row remove control. Each carries an accessible
  name. Anything else is a labeled button.
- **A component primitive is neither look and keeps the one its own component file defines:** the shell's
  top-bar icon controls and rail toggle, a sortable column header, the pager's page buttons and arrows, an
  expandable row's chevron, a tab strip, a segmented control, a toast's single inline action. A current-item
  marker inside one of those, such as the active page or the active tab, is a marker rather than an action's
  emphasis, so it does not count as that group's solid button.

Retired, and never reintroduced under another name: a borderless (ghost) labeled button, an outlined
labeled button, and Cadmium Violet as a button fill. The bundled `ui-ux-design` skill's
`reference/buttons.md` carries the classes, tokens, and CSS; this section is the rule it implements.

## Action Columns Carry Their Labels (mandatory)

Every control in a list's actions column says what it does, in a word, beside its icon: `View`, `Edit`,
`Delete`, `Restore`, `Duplicate`, `Test`. A bare pencil next to a bare key next to a bare trash asks the
user to guess, puts two irreversible actions a few pixels apart with nothing to tell them apart, and
explains itself only through a hover tooltip, which does not exist on a touch device and does nothing for
someone tabbing through the row. An `aria-label` is the floor for assistive technology, not a substitute
for the visible word.

- The label is visible text, not only an accessible name, and it stays at every breakpoint. A narrow
  screen scrolls the actions column with that column pinned rather than degrading to bare icons.
- Where a row genuinely needs more than about three actions, keep the first three labeled inline and move
  the rest behind one `More` overflow control whose accessible name names the row.
- The same holds for a detail header's action cluster and a bulk-selection bar: every action carries its
  word.
- A row's `Delete` opens the confirmation rather than committing, so it is a neutral secondary control
  with a danger-colored label; the dialog's `Delete` is the solid danger action that commits.
- The one icon-only **action** inside a table-shaped control is a form repeater's per-row remove, above,
  where a row is a field group rather than a record and the legend row already says what the column is. A
  sort header, a pager arrow, and a row's expand chevron also sit inside a table without labels, but none
  of them is an action on a record: they are the table's own primitives.

## Component And Interaction Patterns (mandatory)

Stack-neutral minimums for the recurring interaction surfaces. The bundled `ui-ux-design` skill supplies the concrete contract (tokens, sizes, markup, copy); this is the floor every implementation meets.

- Feedback and toasts: confirm an action's result with a non-blocking toast (a toast never interrupts or demands a click; that is a modal). Use a small set of types by meaning (success / error / warning / info), carried with an icon and text, never color alone. Auto-dismiss on a sensible timer (errors persist longer or until dismissed), allow manual dismiss, and announce via an ARIA live region (polite for success/info, assertive for error). Every toast stacks at the **top right** (ENFORCED), in one host offset below the top bar so it never covers the top-bar utilities, newest nearest the top edge, with the polite and assertive regions inside that one host. There is no other placement and no per-screen or per-type choice: bottom-right, top-center, and bottom-center are not options, and a narrow screen widens the same top edge rather than moving to another corner. Success is announced by the toast, not by relabeling the trigger button.
- Modals and dialogs: use a modal only when a decision must happen now; otherwise use a page/route or a toast. Data entry is never a modal, per Data Entry Is Page-Hosted above, so a modal carries at most the few fields that are the decision itself. Every modal honors the behavior contract: `Esc` closes, backdrop click closes (both treated as Cancel), focus is trapped inside, focus returns to the trigger on close, and the background is scroll-locked and inert. Label it (`role="dialog"`, or `role="alertdialog"` for destructive/critical, plus `aria-modal` and `aria-labelledby`/`aria-describedby`). At most one solid action, the one that commits; Cancel and every other labelled action is the neutral secondary; never stack a modal on a modal. Resolve the outcome with a toast (close on success; on error keep the modal with an inline message), never leave it showing a success checkmark.
- Destructive and discard confirmations: a destructive confirmation names the exact target, states consequence and recoverability, uses a verb-labeled danger action (not "OK"/"Yes"), and puts initial focus on the safe action. High-stakes or bulk deletes require friction (type the name / type a confirm word / check "I understand") before the danger action enables. A discard prompt appears only when the form is actually dirty and guards every exit path (Cancel, x, `Esc`, backdrop, in-app navigation). Complements Soft-delete by default.
- Loading, empty, and error states: loading is a skeleton, not a spinner: every structured region (page, panel, table, list, card grid, detail view) shows a skeleton mirroring the incoming layout; avoid a full-region spinner where a skeleton can model the shape. Distinguish the two empty flavors: no-data-yet (first run: explain and offer the primary create action) versus no-results (after a search/filter: explain and offer clear-filters). Error states explain what failed and offer recovery; render an empty data cell as a placeholder, never blank.
- Forms and validation contract: validate error-only (do not decorate valid fields with green ticks); validate inline on blur (do not wait for submit); place the message directly below the field, tied via `aria-describedby`, and reserve the message slot so layout does not jump. After a field has errored, re-validate on input so the error clears when fixed. On submit, block, focus and scroll to the first invalid field, and announce the failure once with an error toast naming how many fields need attention, never with a summary card that repeats the inline messages; map server and async errors back to their own field, and give a form-level error that belongs to no field one persistent inline alert at the form foot, per Validation Reports Once, Where The Error Is above. Every field has a visible associated label (placeholders are not labels); mark required-ness with one consistent convention per form. A step-by-step form adds the draft contract in Step-By-Step Form Drafts above: `Continue` validates the step, saves it, then advances.
- Buttons and async actions: exactly one solid action per action group, and the one neutral secondary look for every other labeled action, per Two Button Looks, Everywhere above. For an async action, on click immediately disable and show an in-place loading affordance, guarding against double-submit; keep the control's width stable; on success return it to rest (confirm via toast); on error return it to rest and surface the error, never trapping it in a permanent loading state. Set `aria-busy` while loading, and pair the spinner with verb-ing text ("Saving...", "Deleting...") for important or destructive operations.
- Breadcrumbs: once a page sits inside a navigation group, show a trail above the page title carrying the **full path from the cluster down**, generated from the navigation config rather than written per page. Keep it plain: an ancestor that resolves to a route is a link, a cluster or group heading has no page of its own so it reads as text, and the current page is the non-link leaf. **The trail is the way back**, so a record page or a record form includes the list it belongs to as a segment and no page carries a separate back link on a row of its own. A page whose whole path is its cluster and itself renders no trail. Keep it honest; it complements the sidebar and never replaces it.
- Tabs (navigation and facets): a horizontal tab strip at the top of the page content is the sanctioned in-canvas switch for exactly two cases: depth overflow beyond the sidebar's three accordion levels, and a single record's or page's facets. Prefer route-backed tabs (each tab its own deep-linkable URL, reflected in the breadcrumb and back/forward), rendered as a navigation landmark of links with `aria-current="page"` on the active tab; reserve the ARIA tab-widget (`role="tablist"`/`tabpanel`) for genuine in-page panels that are not separate routes. Mark the active tab with the brand-accent treatment paired with weight/position, never color alone, and keep it keyboard-reachable. Tabs are terminal (a panel never holds another tab strip or a fresh accordion; if it seems to need sub-tabs, restructure the tree). The strip scrolls horizontally only on small screens (pin `overflow-y: hidden`). Distinct from a list's compact filter control.
- Typed row repeater: when a form section edits a variable-length list of rows (header rows, key/value configuration, typed payload fields, a field builder), render it as a table-style repeater with a labelled legend row, one real labelled control per column, a keyboard-reachable icon-only remove control per row whose accessible name says what it removes (a form repeater row is the sanctioned icon-only case, not a list's actions column - see Action Columns Carry Their Labels above), and an add control that appends an empty row and moves focus into its first field. Server-rendered and script-added rows use identical markup so one set of behaviors covers both. Per-row validation follows the forms contract in the row's reserved message slot; a blank-key row is ignored on save rather than rejected. On small screens the row stacks with its labels shown rather than scrolling horizontally.
- Date and time inputs: prefer the platform's native date/time input styled to the design system over a hand-built calendar popover. Reserve a custom picker for genuinely complex needs (ranges, availability) and keep it fully keyboard-operable.

## Role And Access Model (recommended baseline)

- Five-tier role baseline, highest first: system admin (platform and integration configuration; the only tier that reaches `System Administration`), admin (everything inside the application, including `Application Administration`; the tier that permanently deletes application records), team/collaborator (self + people beneath them), self/contributor (own records only), self-view/read-only (view own, never mutate). Confirmed labels live in `.claude/PROJECT-CONTEXT.md`; rename or extend only for a documented per-app reason, keeping the tier shapes.
- The tiers are cumulative: each sees everything the tier below it sees. System admin exists because the fourth cluster is system-level. Connection strings, API keys, sign-in methods, and model catalogs are not the same responsibility as managing the application's own users, and an app admin who can invite a colleague should not thereby hold every provider credential.
- Default cluster grants, which an app may narrow but should not widen: `Workspace` to every tier; `Compliance` to system admin, admin, and collaborator; `Application Administration` to system admin and admin; `System Administration` to system admin only.
- Defense in depth, all layers must agree: (1) cluster/feature access gates both sidebar visibility and the controller/handler; (2) a per-record policy authorizes a single record's actions (ownership and any state lock); (3) the list/query scope filters records to the user's visible scope so out-of-scope records never load; (4) a hard gate guards permanent delete/purge, admin for application records and system admin for system configuration. Complements least-privilege authorization in `.claude/rules/enterprise-governance.md`; if you grant a tier a cluster, ensure its policy and query scope can serve that cluster's data.

## Quality Bars

- Visual consistency: reuse the approved spacing/sizing scale and type hierarchy; avoid ad hoc values or new sizes/weights.
- Accessibility: meet WCAG AA (the confirmed target); full keyboard navigation; ARIA labels on custom controls; an always-visible focus indicator on every interactive element (never remove a focus outline without an equivalent replacement); respect reduced-motion preferences.
- Interaction and motion: make affordances obvious; keep motion short and purposeful, not decorative.
- Required states: design the empty, loading, and error states for every data-driven view, not only success (complements `.claude/rules/production-readiness.md`). Never ship a blank screen or a lone spinner where a skeleton or placeholder is warranted.
- No templated output: remove filler/lorem-ipsum/stand-in copy before delivering; ship no default or un-themed component styling where the standard applies.

## Conventions And Guardrails

- Status badges: one `state -> classes` map with a neutral fallback; never hard-code a color per status inline.
- Soft-delete by default: destructive deletes route to a recycle bin with a worded confirmation ("Move X to Recycle Bin?"); only admin permanently deletes, and "empty everything" requires a type-to-confirm word.
- Authorization in views: gate row and page actions with policy checks, not merely by hiding links.
- Validation: every field error is inline, below its field and tied with `aria-describedby`; a blocked submit is announced once, by an error toast plus focus on the first invalid field, never by a card that repeats those messages; a form-level error that belongs to no field gets one persistent inline alert at the form foot; always repopulate prior input.
- Pagination: only when there is more than one page, and always after the active sort and filter, over the filtered total.
- Responsive: mobile-first; tables scroll inside a horizontal-scroll container; the sidebar goes off-canvas; complex drag-and-drop authoring may be desktop-only (say so in the UI).
- i18n-ready: externalize user-facing strings and format dates/currency per locale; support RTL where tenants need it.
- Build step: when the styling framework purges unused classes, run the build after editing classes or icons so primitives survive.

## Final Reporting

For UI/UX work, report: which UI states were covered (success, empty, loading, error, small-screen where applicable); that every labeled action used one of the two button looks, which control is each group's one solid action, and that no borderless or outlined labeled button was introduced; that every actions-column control carries its visible word, plus any overflow control used; the archetype(s) used, that standing navigation stayed in the sidebar under the four fixed clusters at 3 or fewer accordion levels and never moved to the top bar, and that any in-canvas tab strip was limited to depth-overflow or record facets; for a breadcrumb, that the trail carried the full path from the cluster, that its ancestors that are pages are links, and that the trail itself is the way back rather than a separate back link; that the shell layout held (full-height sidebar with the top bar over the canvas, a visible divider between the three regions, the rail head matched to the top-bar height, a pinned rail head, and legible collapsed flyouts that escape the scroll clip, use hover-intent, and route in-app); that the approved identity assets were used as-is per theme, placed at the developer-confirmed `<BRAND_ASSETS_PATH>` (asked, never assumed), and that the app-specific values were taken from `.claude/PROJECT-CONTEXT.md` or confirmed, never invented; that the approved tokens, palette, surfaces, and fonts were reused without modification; that the change applied the fixed patterns exactly without inventing a project-specific variant; the role/access layers touched and that they remain consistent; that every create, edit, multi-step, and settings form was page-hosted rather than placed in a modal, and that any dialog carried only the fields that were the decision; for a list / index, the default sort, which columns sort, which facets the filter bar carries, that both run over the whole result set rather than the loaded page, and that the state is in the URL; how a blocked submit was reported (inline messages plus one toast, focus moved to the first invalid field) and that no summary card repeated them; for a step-by-step form, that `Continue` saves the draft before advancing, where the draft is stored and which shape the developer confirmed, how it is resumed and discarded, what happens when the save fails, which fields are excluded from it, and the confirmed retention for an abandoned one; the accessibility check and contrast/target result; that no new styles, components, archetypes, or navigation patterns were invented (or the explicit developer request that authorized a deviation); which interaction patterns applied and which `ui-ux-design` references were consulted.

Final rule: the look and feel is not a per-task decision; the bundled `ui-ux-design` skill's guidelines and token specification are the approved standard, used by default. Deviate only when the developer explicitly asks. If the UI presence, app name, title-bar name, navigation tree, entities, roles, UI stack, or a step-by-step form's draft storage shape and retention is unclear, resolve it from `.claude/PROJECT-CONTEXT.md` or ask; never resolve theme, color, font, logo, or favicon questions by asking or inventing.
