---
name: ui-ux-design
description: Generates UI/UX from the approved CLaaS2SaaS design system - approved design tokens, brand palette, logos, favicons, a persistent sidebar shell, mobile-first responsive layout, and a reusable component library, specified as written guidelines with a bundled brand asset pack. Use when designing or generating any screen, page, layout, or component, when the user mentions this design system, or when they name a specific component (buttons, forms, tables, top bar, side nav, toasts, modals, etc.).
---

# UI/UX Design Skill

Generates UI/UX that is **identical in look and feel** across every application, while allowing **domain-appropriate flexibility** in content, entities, and navigation.

These are the **approved rules** for the design system. **Always use them, by default.** Deviate only when the developer explicitly asks. The layout, both themes, colors, fonts, logos, and favicons are already set; only the app name, title-bar name, navigation tree, entities, roles, and domain copy vary per app.

## How to use this skill (load only what you need)

1. Read this file for the **enforced constants**, **philosophy**, and **global rules** (they apply to every component). This file is deliberately small.
2. The **complete token values** (both themes, every surface/type/shape/elevation/shell dimension) live in `reference/design-tokens.md` - the single source of truth. Read it **once**, the first time you emit styles. Implement those values exactly.
3. Identify which component(s) the screen needs, then open **only** the matching file(s) from the **Component Index**. Each component file is large - **never read the whole `reference/` folder**, and do not open a component file you are not building.
4. Before finalizing, run the relevant component file's Do/Don't checklist.

Every rule is tagged **ENFORCED** (must follow exactly) or **PRINCIPLED** (sensible default; deviation allowed with written justification).

---

## Design philosophy (ENFORCED)

- **Structural DNA** - persistent left sidebar, mobile-first responsive, three-tier layout.
- **Visual language** - 60-30-10 color split, Montserrat headings + Source Sans 3 body.
- **Progressive disclosure** - show only what's needed now; hide advanced behind "show more", a later step, or its own page.
- **Predictable response** - every action has feedback (hover/active/loading); every async op shows loading; every error shows recovery; every success shows confirmation.
- **Apply the standard exactly, every time** - same archetype for a kind of screen, same primitive for a control, same status-role meaning, same icon for an action. Do not reinterpret or invent a variant, and never repurpose a status role. Only app identity, nav tree, entities, roles, and domain content vary - **never** the tokens, palette, fonts, logos, or theme.

**60-30-10:** 60% neutral background (canvas + surfaces) · 30% secondary UI (Cadmium Violet / Jelly Bean Blue / Sunray) · 10% primary accent (Midnight Blue).

---

## Approved brand identity (ENFORCED - never ask, never vary)

Approved constants under `assets/` (next to this file): four per-theme logos and two favicons. Use each asset **as supplied** - never recolour, box, pad, plate, stretch, regenerate, or substitute it.

| Asset | Light theme | Dark theme |
| --- | --- | --- |
| Logo, full wordmark (expanded sidebar) | `logo-full-light.png` | `logo-full-dark.png` |
| Logo, C2S short mark (collapsed rail) | `logo-short-light.png` | `logo-short-dark.png` |
| Favicon (`.ico` 16/32/48/256) | `favicon-light.ico` | `favicon-dark.ico` |

- Company name: **CLaaS2SaaS**. Fonts: headings **Montserrat** (600/700), body **Source Sans 3** (400/500/600/700), Google Fonts import in `reference/design-tokens.md`.
- Logo and favicon **swap with the effective theme**; wire the favicon swap into the theme switcher.
- **Asset destination inside the project is the developer's decision** - recorded in `PROJECT-CONTEXT.md` as `<BRAND_ASSETS_PATH>`. If not recorded, **ask before generating UI that references assets; never choose the location yourself.**
- Per-app (developer supplies, never invented): app name, title-bar name, sidebar structure, brand-assets path, entities, roles, domain copy.

---

## Brand palette (ENFORCED - the constants you reference most)

| Role | Color | Hex |
| --- | --- | --- |
| Primary accent (actions, links, focus, active tab) | Midnight Blue | `#193E6B` |
| Secondary accent (active-nav gold treatment) | Green Gold | `#B3A125` |
| Secondary (badges, chips, non-interactive accents - **not** a button fill) | Cadmium Violet | `#7F3F98` |
| Secondary (non-interactive headers) | Jelly Bean Blue | `#448E9D` |
| semantic.warning | Sunray | `#E9AC53` |
| semantic.success | Avocado Green | `#5F8025` |
| semantic.danger | Violet-Red | `#991547` |
| semantic.info | Jelly Bean Blue | `#448E9D` |

**Color role rules (ENFORCED):** a **button** is either the solid variant of its meaning or the one neutral secondary look, never a second filled color - Cadmium Violet is a badge and chip color, not a button fill (`reference/buttons.md` §4); Jelly Bean Blue is non-interactive only, and Sunray is non-interactive apart from the `btn-warning` fill; destructive is always Violet-Red; Green Gold is the active-nav / highlight treatment only - never warnings or deletion. Never hardcode raw values in components - define tokens once, reference everywhere. Semantic colors used as text/icons go through the theme-aware readable tokens (`--badge-*-fg`), never the raw hex on a surface.

**All surface, text, spacing (4px scale), radius (`4/8/12/16`), and elevation values - for both themes - are in `reference/design-tokens.md`. Do not restate or re-derive them here; read that file when you emit styles.** Every text/surface pair there is WCAG AA verified.

**Theme switcher (REQUIRED).** Profile menu's fixed **Appearance** section: System / Dark / Light segmented control (that order, System default), persisted, with every token defined in both themes and `color-scheme` declared per theme. The two chromes are different colors by design (white vs ink navy); never render the same chrome in both modes.

```css
:root { color-scheme: light; }              /* values in reference/design-tokens.md */
[data-theme="dark"] { color-scheme: dark; } /* dark values, same file */
```

---

## Responsive rule

Design **mobile-first**: base styles for small screens, then layer up. Small screens stack to a single column, the **sidebar goes off-canvas** (slide-in + dimmed backdrop), touch targets **>= 44px**. Width and/or aspect-ratio breakpoints as needed. Avoid JS for responsive layout logic and separate mobile/desktop codebases (pixel/width breakpoints are fine).

---

## Render only functional UI

Do not render UI for not-yet-functional features - **except** not-yet-built **navigation destinations**, which render **disabled with a "Soon" indicator** so planned structure stays visible.

- Unbuilt **nav destination** → **disabled + "Soon"** (kept visible).
- **Unauthorized** (role-gated) anything → **absent** (`render null`), never dimmed.
- Unbuilt **non-nav** control / panel / field → **absent** (omit the markup).
- A group or cluster with zero visible children must not render its header.

---

## Component Index - open ONLY the file you need

| Component | Reference file | Use when |
|-----------|----------------|----------|
| Design tokens (ALL values) | `reference/design-tokens.md` | Any color, surface, font, size, elevation, or dimension |
| Typography | `reference/typography.md` | Font family / weight / size |
| Top bar & side nav (shell) | `reference/topbar-sidenav.md` | Building the shell / primary nav |
| Buttons | `reference/buttons.md` | Any button variant or state |
| Cards | `reference/cards.md` | Card layouts and variants |
| Forms & validation | `reference/forms.md` | Inputs, field sizes, validation states, page-hosted forms, multi-step drafts |
| Tables & pagination | `reference/tables.md` | Data tables, required sorting and filter bar, pagination |
| Modals & dialogs | `reference/modals-dialogs.md` | Confirmations, discard guards, decision dialogs (never a form) |
| Toasts & notifications | `reference/toasts.md` | Success/error feedback |
| Search & filter | `reference/search-filter.md` | Search inputs, filter controls |
| Date & time picker | `reference/date-time-picker.md` | Date/time/range selection |
| Empty & loading states | `reference/empty-and-loading-state.md` | Skeletons, loading, no-data/no-results |
| Breadcrumbs | `reference/breadcrumbs.md` | A page inside a nav group: full path from the cluster, generated from the nav config |
| Tabs (in-canvas) | `reference/tabs.md` | Depth-overflow nav or record facets |
| Icons & logo | `reference/assets.md` | Logo rules, icon registry, asset verification |

Follow each file exactly the same way every time. Open one or two per screen - not the set.

---

## Global anti-patterns (ENFORCED - NEVER)

- Change, re-derive, or "improve" the theme: no new palettes, surfaces, fonts, logos, shadows, or per-app re-skins.
- Move standing navigation to the top bar, or replace the sidebar with top tabs / a hamburger (except mobile collapse). In-canvas content tabs are fine (see `reference/tabs.md`).
- JS-driven responsive logic or separate mobile/desktop codebases (pixel/width breakpoints allowed).
- Hardcode colors or use system fonts (use approved tokens + Montserrat/Source Sans 3).
- Skip loading / empty / error states.
- Put a create, edit, multi-step, or settings form in a modal, a drawer, or an off-canvas panel - data entry is page-hosted (`reference/forms.md` §3).
- Advance a step-by-step form without saving its resumable draft, or keep that draft in browser storage only (`reference/forms.md` §10).
- Ship a list / index with no column sorting or no search and filter bar, sort or filter only the rows already loaded, or keep the sort and filter state out of the URL query (`reference/tables.md` §4, §4a).
- Vary button colors by domain; use Green Gold for destructive; use Jelly Bean Blue for a button, or Sunray for anything but the `btn-warning` fill.
- Give one button role two looks, or use a borderless (ghost) or outlined labeled button - there are two looks only: one solid per action group, and the neutral `btn-secondary` for everything else (`reference/buttons.md` §4).
- Render an actions-column control as a bare icon; every action carries its word beside the icon, at every breakpoint (`reference/buttons.md` §4.2, `reference/tables.md` §6).
- Stack an error-summary card over the inline field messages, or report a blocked submit anywhere but once: inline under each field, plus one error toast and focus on the first invalid field (`reference/forms.md` §6, §7).
- Put a toast anywhere but the top right, or move it by type, screen, or breakpoint (`reference/toasts.md` §6).
- Invent, generate, recolour, or plate a logo; use the bundled per-theme assets as-is.

---

## Deviating from a PRINCIPLED default

State: **Standard pattern** → **Proposed deviation** → **Rationale (2-3 sentences)** → **Domain context** → **Trade-offs acknowledged**. Get sign-off before generating. Anything tagged ENFORCED - every token value, brand asset, and theme decision - is not deviable.
