# UI Verify

Verify a screen you built against the reference layout template before the UI work is called done. The template shows how the approved standard in `.claude/rules/ui-ux-quality.md` looks and behaves on a real page, so a developed screen that differs from it is a defect until it is fixed or recorded as a documented exception. Run it on every screen a task adds or changes, and again after each fix. Run it with `all` to audit every screen of an existing application.

Input: `[SCREEN] [MODE]` - SCREEN is a route, a URL, a component or view file, `changed` (default: every screen this task added or changed), or `all` (every screen in the application). MODE is `browser` (default when a browser tool and a running app are available) or `static`.

## The Reference

- The reference is `00-read-first/05-layout-template/`: `index.html`, `assets/styles.css`, and `assets/app.js`. Routes are hash routes, such as `index.html#tickets`.
- Serve that folder from a local static server and open it over HTTP, because browser tools can block `file://` URLs: run `python -m http.server <PORT> --bind 127.0.0.1` inside the folder, open `http://127.0.0.1:<PORT>/index.html#tickets`, and stop the server when the check is done.
- Custom properties come back as written in the stylesheet (`#193E6B`), while computed colors come back as `rgb(...)`. Normalize both to one form, and hex to one case, before comparing.
- Token values come from `.claude/skills/ui-ux-design/reference/design-tokens.md`, which is the source of truth for every value in both themes. Behavior rules come from `.claude/rules/ui-ux-quality.md`. Where the template and those files disagree, the files win, and the disagreement is reported.
- If `00-read-first/05-layout-template/` is missing from the repository, stop. Tell the developer the full kit, `00-read-first/` included, must be in the repository. Never verify from memory.

## Auditing A Whole Application (`all`)

The UI standard binds existing applications too, not only the screens a task touches, per Existing Applications Conform Too in `.claude/rules/ui-ux-quality.md`. `all` is how an application already built is brought under it.

- Run `/ui-verify all` when the kit is first added to an existing application, when a kit upgrade changes the UI standard, and whenever the developer asks.
- List every screen from the application's own routes and navigation config, map each to its archetype and template route, and run the full comparison on each one. A screen with no matching archetype is itself a finding.
- Write the audit to `doc/ui-verify/ui-audit-<D><Month><YYYY>.md`, for example `ui-audit-29October2026.md`: the day without a leading zero, the month as its full English name, and the year in four digits, the same date format as the EOD reports. Create `doc/ui-verify/` when it is missing. A second audit on the same day updates that file rather than starting another.
- The audit file carries the mode that ran, the screens checked with their template routes, the report table per screen, and a summary listing every mismatch with its status: fixed, authorized as a deviation, or open.
- Every mismatch gets fixed, unless the developer authorizes it in words as a deviation. Where a task backlog exists, the fixes run under Task IDs like any other application change, per `.claude/rules/solution-development-kit.md`. A kit upgrade never contains application code, so the audit runs after the upgrade lands and the fixes follow as their own work.
- Until every mismatch in the latest audit is fixed or authorized, report the application as not conforming to the UI standard.

## Structure, Look, And Behavior, Never Content

- The template is the reference for structure, look, and behavior only. Its sample domain (tickets, assets, SLA, onboarding) is not the application's.
- The app name, the title-bar name, the navigation tree, the entities, and the domain copy come from the App Definition in `.claude/PROJECT-CONTEXT.md`. A different label, a different menu entry, or a different column of data is not a mismatch.
- A different shell, a different archetype layout, a different token value, a different button look, or a different behavior is a mismatch.

## Zero Tolerance

Every value is compared exactly, below a pixel. A difference of 0.1px, or any other fraction of a pixel, in a padding, a position, or a size, one hex digit in a color, a font weight of 500 against 600, a radius of 10px against 12px, a shadow, a line height, or a transition duration is a `Mismatch`, in either theme. There is no "close enough", no rounding, and no judging by eye. A theme color that differs from `design-tokens.md` is a mismatch even when the screen looks right, and so is a hardcoded color that happens to equal a token today, because it will not follow the token when the theme changes.

- Compare at full precision. Computed values and bounding boxes are taken as the browser reports them and never rounded, truncated, or passed through `toFixed`, so `16.5px` against `16px`, and a box 0.1px off the template's, both read as mismatches.
- Compare at the same viewport, the same zoom (100%), and the same device pixel ratio on both pages, so a difference comes from the CSS and not from the measurement.
- Compare computed values, never the source text: `#193E6B`, `#193e6b`, and `rgb(25, 62, 107)` are the same value, and a token that resolves to a different value is different however it is written.
- Width is compared only for fixed-size elements (the sidebar, the rail head, icons, avatars). Content-sized elements compare their height, padding, and every other property, not their width. That holds for the bounding box too: a content-sized element's box width is recorded and not compared.

## Archetype To Template Route

Match each developed screen to its archetype, then compare it against that route. These names are checked against the `REAL` and `PH` maps and `render()` in `assets/app.js`.

| Archetype | Template route |
| --- | --- |
| Shell (every screen) | Any route, for example `#dashboard` |
| Dashboard | `#dashboard` |
| List / index | `#tickets`, also `#assets`, `#users`, `#sla` |
| Detail with facet tabs | `#ticket/INC-1042`, one tab per facet, for example `#ticket/INC-1042/overview` |
| Create / edit form | `#ticket-form` (create) and `#ticket-form/INC-1042` (edit) |
| Step-by-step form | `#onboarding-form` |
| Recycle bin | `#recycle` |
| Settings and connection configuration with test-before-save | `#ch-email` and `#integrations` |
| AI provider catalog | `#models` and `#model-edit` |
| Roles and the grant matrix | `#roles` and `#role-edit` |
| Users and their roles | `#users` |
| Feature registry | `#features` |
| Profile | `#profile` |
| A not-yet-built destination | `#kb` |

The template carries two demo switches worth using. On `#tickets` the `Demo list state` control (`#tkStateSeg`) shows the Data, Empty, Error, and Many states. On `#onboarding-form` the `Demo save outcome` control (`#obSaveSeg`) shows a failed draft save.

The Auth archetype screens, the signed-out page, the no-access page, and the failed sign-in landing, have no template view. Compare them against the Auth archetype in `.claude/rules/ui-ux-quality.md` and the tokens in `design-tokens.md`: a standalone centered card, no shell, the brand mark, the tokens, the fonts, and for the failed sign-in landing one generic message and no route back into authorize.

## browser (default)

Use when a browser tool the organization has approved is available in the session and the developed app is running. Use no other browser tool.

Process:

1. Resolve SCREEN. For `changed`, list every screen this task added or changed from the diff. For `all`, list every screen in the application, per Auditing A Whole Application above. Map each to its archetype and template route from the table above.
2. Open the template route and the developed screen side by side, one page each.
3. Compare every combination of:
   - viewport width 1440, 1024, and 390;
   - the light and the dark theme;
   - the sidebar expanded and collapsed at 1440 and 1024. At 860 pixels wide and below, the template turns the sidebar into an off-canvas drawer, so at 390 compare the drawer closed and open instead.
4. Set the theme on the template through the profile menu, Appearance section, or by setting the `localStorage` key `c2s-theme` and reloading. The template stores the value as JSON, so the value is `"light"` or `"dark"` with the quotes. Collapse the sidebar with the collapse control in the brand block, or set `c2s-collapsed` to `true` and reload. Set the developed app the same way through its own controls.
5. Take a screenshot pair for each combination, and keep the pair together in the report.
6. Measure both pages with computed styles and bounding boxes, not by eye, unrounded. Run this snippet in each page, in each theme, with `SELECTORS` naming the same element on both pages (the template selector from the list below, and the developed app's own selector for the same element):

   ```js
   (() => {
     const SELECTORS = { rail: '#rail', topbar: '.top-nav' /* one entry per element compared */ };
     const PROPS = ['color', 'background-color', 'border-top-color', 'border-right-color',
       'border-bottom-color', 'border-left-color', 'border-top-width', 'border-top-style',
       'border-radius', 'box-shadow', 'font-family', 'font-size', 'font-weight', 'line-height',
       'letter-spacing', 'text-transform', 'padding-top', 'padding-right', 'padding-bottom',
       'padding-left', 'margin-top', 'margin-bottom', 'gap', 'height', 'width', 'opacity',
       'outline-color', 'outline-width', 'outline-offset', 'transition-duration'];
     const root = getComputedStyle(document.documentElement);
     const tokens = {};
     for (const sheet of document.styleSheets) {
       let rules; try { rules = sheet.cssRules; } catch (e) { continue; }
       for (const r of rules) { if (!r.style) continue;
         for (const p of r.style) { if (p.startsWith('--')) tokens[p] = root.getPropertyValue(p).trim(); } }
     }
     const styles = {}, boxes = {};
     for (const [name, sel] of Object.entries(SELECTORS)) {
       const el = document.querySelector(sel);
       if (!el) { styles[name] = null; boxes[name] = null; continue; }
       const cs = getComputedStyle(el);
       styles[name] = Object.fromEntries(PROPS.map(p => [p, cs.getPropertyValue(p)]));
       const b = el.getBoundingClientRect();   // full precision: never round these
       boxes[name] = { x: b.x, y: b.y, width: b.width, height: b.height };
     }
     return { theme: document.documentElement.getAttribute('data-theme'), dpr: devicePixelRatio,
       width: innerWidth, tokens, styles, boxes };
   })()
   ```

   Diff the two results property by property and box value by box value, at full precision. Every property or box value that differs, on any element, in any theme, by any amount, is a row in the report. Also diff every token value the app defines against `design-tokens.md` for that theme; token names may differ by stack, values may not. Measure at least:
   - sidebar width expanded and collapsed (template `#rail`);
   - top bar height (template `.top-nav`), and the rail head (template `#railBrand`) equal to it so the two bottom dividers form one line;
   - the divider lines between sidebar, top bar, and canvas;
   - chrome and canvas backgrounds in each theme;
   - card radius and border (template `.card`);
   - button height, padding, radius, and fill for each of the two looks (template `.btn-primary` and `.btn-secondary`), and the danger label on a row `Delete`;
   - font families, and heading sizes and weights;
   - the table header and the actions column: labels beside the icons, the header right-aligned (template `table.data`);
   - the pagination footer: the page-size dropdown, its height and border, and its place beside the page buttons, at 1440 and at 390;
   - the breadcrumb trail (template `nav.breadcrumb`);
   - the toast position, top right below the top bar (template `.toast-host`);
   - the focus ring on links, buttons, and inputs (template `:focus-visible`);
   - the document's scroll height equal to the viewport height on every screen (`document.documentElement.scrollHeight === innerHeight`), so the page itself never scrolls and only the main area and the nav list do;
   - every segmented control: each segment the same width and height, and the control 32px tall, the height of the field beside it;
   - every chip and tag no wider than its row, with a long label wrapped inside it.
7. Exercise the behavior, not only the first paint: open and close a group, hover a group in the collapsed rail for its flyout, sort a column, apply and clear a filter, change the page size and confirm the list returns to page 1 with the size in the URL, submit an invalid form, trigger a toast, open a confirmation and press `Esc`, and reload to confirm sort and filter state comes back from the URL. Tab through every control at 1440 and at 390 and confirm each focus ring is whole, crosses no neighbouring control, and is never under a sticky column, a sticky header, or the top bar. Edit every create, edit, and settings form, then try each exit (`Cancel`, a sidebar link, a breadcrumb link) and confirm the discard prompt appears.
8. Run the static checks below as well. Browser mode never replaces them.
9. Close the browser when done.

Return: the mode, the screens checked with their template routes, the viewports, themes, and sidebar states compared, the report table, every mismatch flagged with its status (fixed, authorized, or open), the recorded exceptions, the audit file for an `all` run, and whether the UI may now be called done.

## static

Use when no browser tool is available or the app cannot run, and always in addition to browser mode.

Process:

1. Read the developed markup and styles for every screen in scope.
2. Check every token value against `design-tokens.md` exactly, character for character after normalizing case: colors, surfaces in both themes, type, spacing, radii, elevation, motion, and shell dimensions. Any hex, rgb, rgba, hsl, or named color that is not in the token file is off-palette.
3. Search the developed styles and markup for any literal color, size, radius, shadow, or font value outside the token definitions: an inline `style`, a hardcoded hex in a component, a `!important` override, a token redefined in a component scope, or a utility class value that bypasses the tokens. Each one is a mismatch, even when its value equals a token, because it stops following the theme.
4. Check the rules that code can prove or disprove:
   - only the two button looks, one solid action per group, and no borderless or outlined labeled button;
   - every actions-column control carries its visible word beside the icon, at every breakpoint;
   - every list sorts and filters over the whole result set, server-side where paginated, with the state in the URL;
   - every paginated list offers exactly the page sizes 5, 10, 20, 30, 50, 70, 100, defaults to 10, renders the chooser as a native `<select>` with `aria-label="Rows per page"`, never hides it at a small breakpoint, and the server allow-lists `size` and falls back to 10;
   - the base stylesheet carries the one global rule that hides the spin buttons on `input[type="number"]`, no component brings them back, and no phone number, postcode, card number, one-time code, or id uses `type="number"`;
   - every single, standalone yes/no value is an `<input type="checkbox" role="switch">` with a visible label and a visible state word, and a plain checkbox appears only for picking several items from a set;
   - every create, edit, multi-step, and settings form is page-hosted, never a modal, drawer, or off-canvas panel;
   - every create, edit, and settings form guards every exit when dirty (`Cancel`, the sidebar, the breadcrumb, and any other in-app navigation) with the kit's own discard prompt, and no form is left without the guard;
   - no field wrapper inside a form grid sets its own `max-width` or fixed `width`; the grid decides a field's width;
   - every chip or tag carries `max-width: 100%` and wraps a long label inside it;
   - every segmented control comes from the one shared component: equal-width segments (`grid-auto-columns: 1fr`), one height, the 32px control height, and no per-instance or per-id override;
   - the main scroll container is `position: relative`, so no absolutely positioned element, a visually hidden live region included, resolves against the page, and no visually hidden or absolutely positioned live region sits loose in `<body>` (a fixed-position toast host is not one);
   - inside a container that clips or joins its parts (a segmented control, a tab strip), the focus ring is inset (`outline-offset: -4px`), in the accent contrast color on a segment filled with the accent, and every scroll container sets `scroll-padding` of `8px`, more than the outside ring's 4px reach, plus each sticky region on that edge;
   - field errors inline under their fields, a blocked submit announced by one toast, and no summary card;
   - toasts in one host at the top right;
   - the breadcrumb trail is the way back, with no separate back link;
   - icons come from the one registry, in the approved style;
   - brand assets are referenced from `<BRAND_ASSETS_PATH>` as recorded in `.claude/PROJECT-CONTEXT.md`;
   - the app icon is the file recorded as `App icon` in `.claude/PROJECT-CONTEXT.md`, the catalog's `files.icon` value for the most specific node the app is (for example `agentic-crm/agentic-leads-generator.svg` or `eos/integration-hub/icon.svg`), taken from the solution and module icon pack, and a screen that lists solutions or modules gives each item its own node's icon, never an `outline.svg` or `in-parent.svg` the developer did not ask for; each embedded inline, from one sprite, or as a mask (never `<img>`), colored by `--solution-icon`, at the standard sizes (20px in the top bar, 48px on the auth cards, 24px in lists, 40px on cards and tiles);
   - the four fixed clusters in their fixed order, at most three accordion levels, and the top bar carries no navigation.
5. Record a file and line for every finding.

Return: the mode, the screens checked with their template routes, the report table, every mismatch flagged with its status (fixed, authorized, or open), the recorded exceptions, the audit file for an `all` run, a plain statement that the visual comparison did not run, the list of what stays unverified because of that, and whether the UI may now be called done.

## Checklist

Give every area exactly one verdict, `Match`, `Mismatch`, or `Not applicable`, with its evidence.

- Shell and navigation: full-height sidebar owning the top-left corner, top bar over the canvas only, the three dividers, the rail head height, the pinned rail head, collapse and expand, flyouts, the four clusters, the drawer at 390, the top-bar app icon left of the app name when one is recorded, and a document that never scrolls past the viewport.
- Tokens and surfaces in both themes: every token value equal to `design-tokens.md`, chrome, canvas, cards, text, muted text, status colors through the readable tokens, and no hardcoded color anywhere.
- Typography: Montserrat headings, Source Sans 3 body, the sizes and weights in the token file.
- Buttons: the two looks, one solid per group, the shared geometry, the danger label on a row `Delete`.
- Archetype structure: the page header, the breadcrumb, and the card layout of the matching archetype.
- Lists: sortable headers with `aria-sort`, the declared default sort, the filter bar and its facets, the labeled actions column, the pagination footer with the native page-size dropdown offering 5, 10, 20, 30, 50, 70, 100 and defaulting to 10, shown once the filtered total is more than 5 and visible at every breakpoint including 390, page buttons only past one page, and the state, page size included, in the URL.
- Forms and validation: page-hosted, section cards, `Cancel` beside the one solid submit, inline errors on blur, one toast on a blocked submit, no spin buttons on any number input, digit strings that are not quantities as `type="text"` with `inputmode="numeric"`, every single yes/no value as a switch with its state word, field width set by the form grid and never by a wrapper, the discard prompt on every exit of a dirty form, and for a step-by-step form the vertical step rail and the draft saved on `Continue`.
- Feedback: toasts top right, modals only for decisions, confirmations that name the target and focus the safe action.
- States: skeleton loading, the two empty flavors (no data yet and no results), and the error state with recovery.
- Responsive: 1440, 1024, and 390, with no sideways scroll outside a table container.
- Accessibility: the visible focus ring, never clipped, never crossing a neighbour, and never under a sticky region or the top bar, labels on every field and custom control, contrast, keyboard reach, and reduced motion.

## Report

Report as one table per screen:

| Area | Template | Developed | Verdict | File and line | Fix |
| --- | --- | --- | --- | --- | --- |

- The Template and Developed columns carry the measured value or the observed behavior, not an opinion. For example, `top bar 52px` beside `top bar 60px`.
- Any `Mismatch` means the UI work is not done, however small the difference. 0.1px is not small enough to pass.
- Flag every mismatch found, in this report and again in the task's final summary, whether it was fixed, authorized, or is still open. Never fix one silently and never leave one out because it was fixed.
- Fix each mismatch in the developed code, then run the comparison again over that screen. Repeat until every area reads `Match` or `Not applicable`.
- A deliberate deviation is allowed only when the developer explicitly authorizes it, in words, for that named mismatch, recorded as a documented exception in `.claude/PROJECT-CONTEXT.md`, per `.claude/rules/ui-ux-quality.md`. Silence, a general go-ahead, and your own judgment that the difference is acceptable are never authorization. Name the exception in the row and mark it as such.

## Always

- Say which mode ran. A static-only run says plainly that the visual comparison did not run, and lists what stays unverified, such as rendered spacing, theme surfaces, collapse animation, flyout placement, and toast position.
- Never report a visual match from reading code. A match is something both pages were measured or seen to do.
- Evidence that would look the same if the UI were wrong is not evidence. A screenshot of one theme does not prove the other, a first paint does not prove behavior, and a wide viewport does not prove a narrow one.
- Never verify from memory, and never treat the template's sample content as a requirement.
- Never put secrets, customer data, or production values in a screenshot or the report, per `.claude/rules/secret-handling.md`. Use synthetic data in the developed app while comparing.
