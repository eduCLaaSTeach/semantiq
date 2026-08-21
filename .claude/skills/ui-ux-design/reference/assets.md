# Icons & Logo (Brand Assets)

The logo (source, rules, dimensions) and the icon library (which library, style, sizing, naming). Use when placing the logo, choosing/importing an icon, or verifying assets exist. Component files reference this file for the **system**; they own their own element-to-asset **mapping** (e.g. which icon goes on which nav item).

Icons come from the skill's bundled inline-SVG registry; logos/favicons ship with this skill under `../assets/`. The logo's home is the **sidebar brand block** (see `topbar-sidenav.md`).

## Contents

- Logo
- Icons
- Icon catalog (common)
- Asset verification
- Other assets
- Do / Don't

---

## Logo

The CLaaS2SaaS logos are approved brand assets bundled with this skill. **Never modify, recolour, distort, plate, or re-typeset them.**

| Property | Value |
| --- | --- |
| Official source | This skill's asset pack: `.claude/skills/ui-ux-design/assets/` (do not download elsewhere or regenerate) |
| In-project destination | Developer's decision, recorded as `<BRAND_ASSETS_PATH>` in `.claude/PROJECT-CONTEXT.md`. If unrecorded, **ask the developer where to put the files; never choose the location yourself** (they may also copy the files manually and record the path) |
| Files (per theme) | Wide wordmark `logo-full-light.png` (navy+gold, light chrome) / `logo-full-dark.png` (white+gold, dark chrome); collapsed C2S marks `logo-short-light.png` / `logo-short-dark.png` |
| Favicon (per theme) | `favicon-light.ico` / `favicon-dark.ico` - multi-size `.ico` (16/32/48/256), swapped with the effective theme |
| Display size | Wide logo `height: 22px; width: auto` in the 52px rail head; collapsed C2S mark in a 40x34 slot with `object-fit: contain` |
| Placement | **Sidebar brand block** - wide logo expanded, C2S mark collapsed; full spec in `topbar-sidenav.md` |
| Alt text | "CLaaS2SaaS" |
| Variants | Light variants on the light (white) chrome; dark (white) variants on the dark (ink) chrome - swap with the effective theme |

The logo lives in the sidebar brand block, not the top bar. Use the wide wordmark when the sidebar is expanded and the C2S short mark when collapsed, each in the theme-matching variant.

---

## Icons

- **Library (fixed):** one central inline-SVG icon registry for the app - a single `<symbol>` sprite (or the stack's equivalent icon-component registry) whose glyphs all follow the fixed style: 24px viewBox, 2px stroke, round caps/joins, outline. Do not mix libraries (no second icon set, emoji, or ad-hoc SVGs); add a genuinely new glyph to the registry in the same style.
- **Style:** outline (regular) is the default for navigation and content. Use a filled treatment only for a deliberately emphasised/active affordance, consistently.
- **Naming convention:** registry symbol ids are `i-<concept>` (e.g. `i-grid`, `i-trash`, `i-chevron-right`); keep new glyphs on the same convention.
- **Sizing:** icons render at `1em` (`width: 1em; height: 1em`) and are sized via `font-size`, not by scaling the SVG.

| Context | Size |
| --- | --- |
| Nav item / group icon | 18px (20px when rail collapsed) |
| Top-bar action icon (e.g. bell, gear) | 20px |
| Chevrons (expand/collapse) | 16px |
| Empty-state illustration icon | 48px |

**Central registry:** render icons from the one central registry (the bundled SVG sprite or its framework wrapper). Register an icon before use; keep icons meaningful, never decorative filler.

```html
<!-- reference the sprite symbol from the bundled registry -->
<svg class="ic" aria-hidden="true"><use href="#i-bell" /></svg>
```

**Accessibility:** decorative icons get `aria-hidden="true"`. An icon that is the only content of a control needs an accessible name on it (`aria-label`), and icon-only is limited to the closed chrome list in `buttons.md` §4.1 - an action the user picks between, including every actions-column control, carries its visible word beside the icon (`buttons.md` §4.2).

---

## Icon catalog (common)

Frequently-used icon *concepts* and their meaning. This is the **catalog** of semantic roles; the authoritative element-to-icon **mapping** lives in each component file (e.g. the shell mapping is in `topbar-sidenav.md`). Map each concept to the matching symbol in the bundled registry; add a missing glyph in the same style.

| Icon concept | General use |
| --- | --- |
| An alert/bell icon | Notifications |
| A settings/gear icon | Settings / configuration |
| A globe icon | Environment / region |
| A person/profile icon | User / profile |
| A person-with-arrow icon | User assignment / hand-off |
| A sign-out icon | Logout |
| A key icon | Access / credentials |
| A shield-checkmark icon | Security |
| A grid icon | Dashboard / overview |
| An apps icon | Modules |
| A table icon | Tabular data / configuration |
| A clipboard task-list icon | Audit / task list |
| A headset icon | Helpdesk / support |
| A scales icon | Governance / compliance |
| A chevron-down icon | Accordion group expand / collapse (and the collapsed-group flyout hint) |
| A panel / sidebar-toggle icon (`i-panel`) | Rail collapse **and** expand - the same glyph in both states |
| A search icon | Search |
| A filter funnel icon (outlined / solid) | Filter (inactive / active) |
| A sun / moon icon | Appearance / theme switch (light / dark) |
| A close (x) icon | Close / dismiss |
| An eye icon | View / open read-only |
| A pencil icon | Edit |
| A copy icon | Duplicate |
| A trash icon | Delete |
| A restore icon | Restore from the recycle bin |
| A kebab / horizontal-dots icon | Row overflow ("More"), only past about three row actions |

---

## Asset verification

Before generating UI, confirm required assets are in place: the **logo/favicon pack** copied from `../assets/`, the **icon registry** (bundled SVG sprite), **fonts** (Montserrat + Source Sans 3, see `typography.md`), and the **design tokens** (`design-tokens.md`).

If an asset is missing:
1. Generate with a placeholder.
2. Mark it: `<!-- ASSET MISSING: [asset] - replace with actual path -->`.
3. Tell the user plainly: which asset, where it's expected, and the impact.

```
⚠️ Missing asset: [name]
Expected at: [path/source]
Impact: [what is affected]
Provide the file or correct path to finalise.
```

---

## Other assets

- **Fonts** (Montserrat, Source Sans 3) - owned by `typography.md` (families + import).
- **Design tokens** (colors, spacing, radius, shadow) - owned by the router `SKILL.md`, with complete values in `design-tokens.md`.

---

## Do / Don't

**Do**
- Use the bundled per-theme logos unmodified (wide `height: 22px; width: auto`; C2S mark in a 40x34 `object-fit: contain` slot), copied from the skill's asset pack.
- Swap the logo and favicon variants with the effective theme.
- Use the bundled SVG icon registry exclusively, outline style by default, sized via `font-size`.
- Give decorative icons `aria-hidden`; give icon-only chrome controls an accessible name, and give every labeled action its word.

**Don't**
- Recolour, restretch, plate, or re-typeset the logo, or source it from anywhere but the bundled pack.
- Mix icon libraries (no second icon set, emoji, or one-off SVGs outside the registry).
- Scale icons by transforming the SVG instead of setting `font-size`.
- Invent, generate, or substitute a logo or favicon; the approved assets ship with this skill.
